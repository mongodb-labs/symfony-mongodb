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

namespace MongoDB\Bundle\Tests\Unit\Attribute;

use MongoDB\Bundle\Attribute\AutowireClient;
use MongoDB\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Reference;

/** @covers \MongoDB\Bundle\Attribute\AutowireClient */
final class AutowireClientTest extends TestCase
{
    public function testMinimal(): void
    {
        $autowire = new AutowireClient();

        $this->assertEquals(new Reference(Client::class), $autowire->value);
    }

    public function testWithClientId(): void
    {
        $autowire = new AutowireClient('foobar');

        $this->assertEquals(new Reference('mongodb.client.foobar'), $autowire->value);
    }
}
