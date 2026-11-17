<?php

declare(strict_types=1);

/**
 * Copyright 2023-present MongoDB, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace MongoDB\Bundle\DataCollector;

use LogicException;
use MongoDB\Client;
use MongoDB\Driver\Command;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Throwable;
use WeakMap;

use function array_diff_key;
use function count;

/** @internal */
final class MongoDBDataCollector extends DataCollector implements LateDataCollectorInterface, CommandEventCollector
{
    /**
     * The list of request by client ID is built with driver event data.
     *
     * @var array<int, array<string, array{clientName?:string,databaseName:string,commandName:string,command:array,operationId?:int,serviceId?:int,backtrace?:array,durationMicros?:int,getMoreCount?:int,getMoreDurationMicros?:int,getMoreCommands?:list<array{durationMicros?:int,error?:string}>,error?:string}>>
     */
    private array $requests = [];

    /**
     * The request that opened each cursor, by client ID.
     *
     * @var array<int, array<int, string>>
     */
    private array $cursors = [];

    /**
     * The parent request and the index in its getMore list for each getMore command, by client ID.
     *
     * @var array<int, array<string, array{0:string, 1:int}>>
     */
    private array $getMoreRequests = [];

    /**
     * The identifier assigned to each client. A WeakMap is used because the
     * identifier of a client must not be reused by another client instance.
     *
     * @var WeakMap<Client, int>
     */
    private WeakMap $clientIds;

    private int $nextClientId = 0;

    public function __construct(
        private readonly ?Stopwatch $stopwatch = null,
        /** @var iterable<string, Client> */
        private readonly iterable $clients = [],
    ) {
        $this->clientIds = new WeakMap();
    }

    public function configureClient(Client $client): void
    {
        $client->getManager()->addSubscriber(new DriverEventSubscriber($this->getClientInternalId($client), $this, $this->stopwatch));
    }

    /** @internal */
    public function getClientInternalId(Client $client): int
    {
        $this->clientIds[$client] ??= ++$this->nextClientId;

        return $this->clientIds[$client];
    }

    public function collectCommandEvent(int $clientId, string $requestId, array $data): void
    {
        if (isset($this->getMoreRequests[$clientId][$requestId])) {
            [$parentRequestId, $index] = $this->getMoreRequests[$clientId][$requestId];
            $this->collectGetMoreEvent($clientId, $parentRequestId, $index, $data);

            return;
        }

        if (isset($data['cursorId'])) {
            if (($data['commandName'] ?? null) === 'getMore') {
                // Group the getMore command with the request that opened the cursor
                $cursorRequestId = $this->cursors[$clientId][$data['cursorId']] ?? null;
                if ($cursorRequestId !== null && isset($this->requests[$clientId][$cursorRequestId])) {
                    $this->requests[$clientId][$cursorRequestId]['getMoreCommands'][] = [];
                    $this->requests[$clientId][$cursorRequestId]['getMoreCount'] = ($this->requests[$clientId][$cursorRequestId]['getMoreCount'] ?? 0) + 1;

                    $index = count($this->requests[$clientId][$cursorRequestId]['getMoreCommands']) - 1;
                    $this->getMoreRequests[$clientId][$requestId] = [$cursorRequestId, $index];

                    return;
                }
            } elseif (
                $data['cursorId'] > 0
                && ($this->requests[$clientId][$requestId]['commandName'] ?? null) !== 'getMore'
            ) {
                $this->cursors[$clientId][$data['cursorId']] = $requestId;
            }
        }

        if (isset($this->requests[$clientId][$requestId])) {
            $this->requests[$clientId][$requestId] += $data;
        } else {
            $this->requests[$clientId][$requestId] = $data;
        }
    }

    private function collectGetMoreEvent(int $clientId, string $requestId, int $index, array $data): void
    {
        if (! isset($this->requests[$clientId][$requestId]['getMoreCommands'][$index])) {
            return;
        }

        if (isset($data['durationMicros'])) {
            $this->requests[$clientId][$requestId]['getMoreCommands'][$index]['durationMicros'] = ($this->requests[$clientId][$requestId]['getMoreCommands'][$index]['durationMicros'] ?? 0) + $data['durationMicros'];
            $this->requests[$clientId][$requestId]['durationMicros'] = ($this->requests[$clientId][$requestId]['durationMicros'] ?? 0) + $data['durationMicros'];
            $this->requests[$clientId][$requestId]['getMoreDurationMicros'] = ($this->requests[$clientId][$requestId]['getMoreDurationMicros'] ?? 0) + $data['durationMicros'];
        }

        if (! isset($data['error'])) {
            return;
        }

        $this->requests[$clientId][$requestId]['getMoreCommands'][$index]['error'] = $data['error'];
        $this->requests[$clientId][$requestId]['error'] = $data['error'];
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $requestCount = 0;
        $errorCount = 0;
        $getMoreCount = 0;
        $durationMicros = 0;

        $clients = [];
        $clientIdMap = [];
        foreach ($this->clients as $name => $client) {
            $clientIdMap[$this->getClientInternalId($client)] = $name;
            $clients[$name] = [
                'serverBuildInfo' => array_diff_key(
                    (array) $client->getManager()->executeCommand('admin', new Command(['buildInfo' => 1]))->toArray()[0],
                    ['versionArray' => 0, 'ok' => 0],
                ),
                'clientInfo' => array_diff_key($client->__debugInfo(), ['manager' => 0, 'builderEncoder' => 0]),
            ];
        }

        $requests = [];
        foreach ($this->requests as $clientId => $requestsByClientId) {
            $clientName = $clientIdMap[$clientId] ?? throw new LogicException('Client not found');
            foreach ($requestsByClientId as $requestId => $request) {
                $requests[$clientName][$requestId] = $request;
                $requestCount++;
                $getMoreCount += $request['getMoreCount'] ?? 0;
                $durationMicros += $request['durationMicros'] ?? 0;
                $errorCount += isset($request['error']) ? 1 : 0;
            }
        }

        $this->data = [
            'clients' => $clients,
            'requests' => $requests,
            'requestCount' => $requestCount,
            'errorCount' => $errorCount,
            'getMoreCount' => $getMoreCount,
            'durationMicros' => $durationMicros,
        ];
    }

    public function getRequestCount(): int
    {
        return $this->data['requestCount'];
    }

    public function getErrorCount(): int
    {
        return $this->data['errorCount'];
    }

    public function getGetMoreCount(): int
    {
        return $this->data['getMoreCount'];
    }

    public function getTime(): int
    {
        return $this->data['durationMicros'];
    }

    public function getRequests(): array
    {
        return $this->data['requests'];
    }

    public function getClients(): array
    {
        return $this->data['clients'];
    }

    public function getName(): string
    {
        return 'mongodb';
    }

    public function reset(): void
    {
        $this->requests = [];
        $this->cursors = [];
        $this->getMoreRequests = [];
        $this->data = [];
    }
}
