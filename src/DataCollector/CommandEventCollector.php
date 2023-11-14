<?php

declare(strict_types=1);

namespace MongoDB\Bundle\DataCollector;

/** @internal */
interface CommandEventCollector
{
    public function collectCommandEvent(int $clientId, string $requestId, array $data): void;
}
