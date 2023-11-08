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

use MongoDB\Bundle\Client;
use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class MongoDBDataCollector extends DataCollector
{
    /**
     * @var list<array{client:Client, subscriber:DriverEventSubscriber}>
     */
    private array $clients = [];

    public function addClient(string $name, Client $client, DriverEventSubscriber $subscriber): void
    {
        $this->clients[$name] = [
            'client' => $client,
            'subscriber' => $subscriber,
        ];
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        foreach ($this->clients as $name => ['client' => $client, 'subscriber' => $subscriber]) {
            $totalTime = 0;
            $requestCount = 0;
            $errorCount = 0;
            $requests = [];

            foreach ($subscriber->getEvents() as $event) {
                $requestId = $event->getRequestId();

                if ($event instanceof CommandStartedEvent) {
                    $command = (array) $event->getCommand();
                    unset($command['lsid'], $command['$clusterTime']);

                    $requests[$requestId] = [
                        'client' => $name,
                        'startedAt' => hrtime(true),
                        'commandName' => $event->getCommandName(),
                        'command' => $command,
                        // 'server' => $event->getServer()->getInfo(),
                        'operationId' => $event->getOperationId(),
                        'database' => $event->getDatabaseName(),
                        'serviceId' => $event->getServiceId(),
                    ];
                    ++$requestCount;
                } elseif ($event instanceof CommandSucceededEvent) {
                    $requests[$requestId] += [
                        // 'reply' => Document::fromPHP($event->getReply()),
                        'duration' => $event->getDurationMicros(),
                        'endedAt' => hrtime(true),
                        'success' => true,
                    ];
                    $totalTime += $event->getDurationMicros();
                } elseif ($event instanceof CommandFailedEvent) {
                    $requests[$requestId] += [
                        // 'reply' => Document::fromPHP($event->getReply()),
                        'duration' => $event->getDurationMicros(),
                        'error' => $event->getError(),
                        'success' => false,
                    ];
                    $totalTime += $event->getDurationMicros();
                    ++$errorCount;
                }
            }

            $this->data['clients'][$name] = [
                'name' => $name,
                'uri' => (string) $client,
                'totalTime' => $totalTime,
                'requestCount' => $requestCount,
                'errorCount' => $errorCount,
                'requests' => $requests,
            ];
        }
    }

    public function getRequestCount(): int
    {
        return array_sum(array_column($this->data['clients'], 'requestCount'));
    }

    public function getErrorCount(): int
    {
        return array_sum(array_column($this->data['clients'], 'errorCount'));
    }

    public function getTime(): float
    {
        return array_sum(array_column($this->data['clients'], 'totalTime'));
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
        // TODO: Implement reset() method.
    }
}
