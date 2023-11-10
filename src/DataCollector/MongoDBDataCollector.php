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

use MongoDB\Client;
use MongoDB\Driver\Command;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Throwable;

use function array_column;
use function array_diff_key;
use function array_map;
use function array_sum;
use function count;
use function debug_backtrace;
use function dump;
use function iterator_to_array;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

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
        /** @var iterable<string, Client> */
        private readonly iterable $clients = [],
    ) {
    }

    public function collectCommandEvent(string $clientName, string $requestId, array $data): void
    {
        if (isset($this->requests[$clientName][$requestId])) {
            $this->requests[$clientName][$requestId] += $data;
        } else {
            $this->requests[$clientName][$requestId] = $data;
        }
    }

    public function collect(Request $request, Response $response, ?Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data = [
            'clients' => array_map(static fn (Client $client) => [
                'serverBuildInfo' => $client->getManager()->executeCommand('admin', new Command(['buildInfo' => 1]))->toArray()[0],
                'clientInfo' => array_diff_key($client->__debugInfo(), ['manager' => 1]),
            ], iterator_to_array($this->clients)),
            'requests' => $this->requests,
            'requestCount' => array_sum(array_map(count(...), $this->requests)),
            'errorCount' => array_sum(array_map(static fn (array $requests) => count(array_column($requests, 'error')), $this->requests)),
            'durationMicros' => array_sum(array_map(static fn (array $requests) => array_sum(array_column($requests, 'durationMicros')), $this->requests)),
        ];

        dump($this->data, array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS), 'class'));
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
