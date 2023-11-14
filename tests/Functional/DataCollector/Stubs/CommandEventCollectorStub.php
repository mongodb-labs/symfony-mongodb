<?php

declare(strict_types=1);

namespace MongoDB\Bundle\Tests\Functional\DataCollector\Stubs;

use MongoDB\Bundle\DataCollector\CommandEventCollector;

class CommandEventCollectorStub implements CommandEventCollector
{
    public array $events;

    public function collectCommandEvent(int $clientId, string $requestId, array $data): void
    {
        $this->events[] = ['clientId' => $clientId, 'requestId' => $requestId, 'data' => $data];
    }
}
