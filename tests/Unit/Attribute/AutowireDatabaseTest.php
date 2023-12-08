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

use MongoDB\Bundle\Attribute\AutowireDatabase;
use MongoDB\Client;
use MongoDB\Database;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Reference;

/** @covers \MongoDB\Bundle\Attribute\AutowireDatabase */
final class AutowireDatabaseTest extends TestCase
{
    public function testMinimal(): void
    {
        $autowire = new AutowireDatabase();

        $this->assertEquals([new Reference(Client::class), 'selectDatabase'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Database::class,
            parameter: new ReflectionParameter(
                static function (Database $mydb): void {
                },
                'mydb',
            ),
        );

        $this->assertSame(Database::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame('%MongoDB\Client.default_database%', $definition->getArgument(0));
    }

    public function testDatabase(): void
    {
        $autowire = new AutowireDatabase(
            database: 'mydb',
            client: 'default',
            options: ['foo' => 'bar'],
        );

        $this->assertEquals([new Reference('mongodb.client.default'), 'selectDatabase'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Database::class,
            parameter: new ReflectionParameter(
                static function (Database $db): void {
                },
                'db',
            ),
        );

        $this->assertSame(Database::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame('mydb', $definition->getArgument(0));
        $this->assertSame(['foo' => 'bar'], $definition->getArgument(1));
    }

    public function testWithoutDatabase(): void
    {
        $autowire = new AutowireDatabase(
            client: 'default',
            options: ['foo' => 'bar'],
        );

        $this->assertEquals([new Reference('mongodb.client.default'), 'selectDatabase'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Database::class,
            parameter: new ReflectionParameter(
                static function (Database $mydb): void {
                },
                'mydb',
            ),
        );

        $this->assertSame(Database::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame('%mongodb.client.default.default_database%', $definition->getArgument(0));
        $this->assertSame(['foo' => 'bar'], $definition->getArgument(1));
    }
}
