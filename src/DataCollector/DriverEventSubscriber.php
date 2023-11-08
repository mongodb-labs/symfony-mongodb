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
use Symfony\Contracts\Service\ResetInterface;

/** @internal */
final class DriverEventSubscriber implements CommandSubscriber, ResetInterface
{
    /**
     * @var list<CommandFailedEvent|CommandStartedEvent|CommandSucceededEvent>
     */
    private array $events = [];
    private array $stopwatchEvents = [];

    public function __construct(
        private string $clientName,
        private ?Stopwatch $stopwatch = null,
    ) {
    }

    /**
     * @return list<CommandFailedEvent|CommandStartedEvent|CommandSucceededEvent>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    public function commandFailed(CommandFailedEvent $event): void
    {
        $this->events[] = $event;

        if (isset($this->stopwatchEvents[$event->getRequestId()])) {
            $this->stopwatchEvents[$event->getRequestId()]->stop();
            unset($this->stopwatchEvents[$event->getRequestId()]);
        }
    }

    public function commandStarted(CommandStartedEvent $event): void
    {
        $this->events[] = $event;

        if ($this->stopwatch) {
            $this->stopwatchEvents[$event->getRequestId()] = $this->stopwatch->start(
                'mongodb.'.$this->clientName.'.'.$event->getCommandName(),
                'mongodb',
            );
        }
    }

    public function commandSucceeded(CommandSucceededEvent $event): void
    {
        $this->events[] = $event;

        if (isset($this->stopwatchEvents[$event->getRequestId()])) {
            $this->stopwatchEvents[$event->getRequestId()]->stop();
            unset($this->stopwatchEvents[$event->getRequestId()]);
        }
    }

    public function reset(): void
    {
        $this->events = [];
    }
}
