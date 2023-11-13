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

use MongoDB\Driver\Monitoring\CommandFailedEvent;
use MongoDB\Driver\Monitoring\CommandStartedEvent;
use MongoDB\Driver\Monitoring\CommandSubscriber;
use MongoDB\Driver\Monitoring\CommandSucceededEvent;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

use function array_shift;
use function debug_backtrace;

use const DEBUG_BACKTRACE_IGNORE_ARGS;

/** @internal */
final class DriverEventSubscriber implements CommandSubscriber
{
    /** @var array<string, StopwatchEvent> */
    private array $stopwatchEvents = [];

    public function __construct(
        private readonly int $clientId,
        private readonly MongoDBDataCollector $dataCollector,
        private readonly ?Stopwatch $stopwatch = null,
    ) {
    }

    public function commandStarted(CommandStartedEvent $event): void
    {
        $requestId = $event->getRequestId();

        $command = (array) $event->getCommand();
        unset($command['lsid'], $command['$clusterTime']);

        $this->dataCollector->collectCommandEvent($this->clientId, $requestId, [
            'databaseName' => $event->getDatabaseName(),
            'commandName' => $event->getCommandName(),
            'command' => $command,
            'operationId' => $event->getOperationId(),
            'serviceId' => $event->getServiceId(),
            'backtrace' => $this->filterBacktrace(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)),
        ]);

        $this->stopwatchEvents[$requestId] = $this->stopwatch?->start(
            'mongodb.' . $event->getCommandName(),
            'mongodb',
        );
    }

    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $requestId = $event->getRequestId();

        $this->stopwatchEvents[$requestId]?->stop();
        unset($this->stopwatchEvents[$requestId]);

        $this->dataCollector->collectCommandEvent($this->clientId, $requestId, [
            'durationMicros' => $event->getDurationMicros(),
        ]);
    }

    public function commandFailed(CommandFailedEvent $event): void
    {
        $requestId = $event->getRequestId();

        $this->stopwatchEvents[$requestId]?->stop();
        unset($this->stopwatchEvents[$requestId]);

        $this->dataCollector->collectCommandEvent($this->clientId, $requestId, [
            'durationMicros' => $event->getDurationMicros(),
            'error' => $event->getError(),
        ]);
    }

    private function filterBacktrace(array $backtrace): array
    {
        // skip first since it's always the current method
        array_shift($backtrace);

        return $backtrace;
    }
}
