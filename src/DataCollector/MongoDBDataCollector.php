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

use function array_diff_key;
use function spl_object_id;

/** @internal */
final class MongoDBDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /**
     * The list of request by client name is built with driver event data.
     *
     * @var array<string, array<string, array{clientName:string,databaseName:string,commandName:string,command:array,operationId:int,serviceId:int,durationMicros?:int,error?:string}>>
     */
    private array $requests = [];

    public function __construct(
        private readonly ?Stopwatch $stopwatch = null,
        /** @var iterable<string, Client> */
        private readonly iterable $clients = [],
    ) {
    }

    public function configureClient(Client $client): void
    {
        $client->getManager()->addSubscriber(new DriverEventSubscriber(spl_object_id($client), $this, $this->stopwatch));
    }

    public function collectCommandEvent(int $clientId, string $requestId, array $data): void
    {
        if (isset($this->requests[$clientId][$requestId])) {
            $this->requests[$clientId][$requestId] += $data;
        } else {
            $this->requests[$clientId][$requestId] = $data;
        }
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $requestCount = 0;
        $errorCount = 0;
        $durationMicros = 0;

        $clients = [];
        $clientIdMap = [];
        foreach ($this->clients as $name => $client) {
            $clientIdMap[spl_object_id($client)] = $name;
            $clients[$name] = [
                'serverBuildInfo' => array_diff_key(
                    (array) $client->getManager()->executeCommand('admin', new Command(['buildInfo' => 1]))->toArray()[0],
                    ['versionArray' => 0, 'ok' => 0],
                ),
                'clientInfo' => array_diff_key($client->__debugInfo(), ['manager' => 0]),
            ];
        }

        $requests = [];
        foreach ($this->requests as $clientId => $requestsByClientId) {
            $clientName = $clientIdMap[$clientId] ?? throw new LogicException('Client not found');
            foreach ($requestsByClientId as $requestId => $request) {
                $requests[$clientName][$requestId] = $request;
                $requestCount++;
                $durationMicros += $request['durationMicros'] ?? 0;
                $errorCount += isset($request['error']) ? 1 : 0;
            }
        }

        $this->data = [
            'clients' => $clients,
            'requests' => $requests,
            'requestCount' => $requestCount,
            'errorCount' => $errorCount,
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
        $this->data = [];
    }
}
