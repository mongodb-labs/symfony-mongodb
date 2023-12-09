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

use MongoDB\BSON\Document;
use MongoDB\Bundle\Attribute\AutowireCollection;
use MongoDB\Client;
use MongoDB\Codec\DecodeIfSupported;
use MongoDB\Codec\DocumentCodec;
use MongoDB\Codec\EncodeIfSupported;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Reference;

use function sprintf;

/** @covers \MongoDB\Bundle\Attribute\AutowireCollection */
final class AutowireCollectionTest extends TestCase
{
    public function testMinimal(): void
    {
        $autowire = new AutowireCollection();

        $this->assertEquals([new Reference($client = Client::class), 'selectCollection'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Collection::class,
            parameter: new ReflectionParameter(
                static function (Collection $fooBar): void {
                },
                'fooBar',
            ),
        );

        $this->assertSame(Collection::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame(sprintf('%%%s.default_database%%', $client), $definition->getArgument(0));
        $this->assertSame('fooBar', $definition->getArgument(1));
        $this->assertSame([], $definition->getArgument(2));
    }

    public function testCollection(): void
    {
        $autowire = new AutowireCollection(
            collection: 'test',
            database: 'mydb',
            client: 'default',
            options: ['foo' => 'bar'],
        );

        $this->assertEquals([new Reference('mongodb.client.default'), 'selectCollection'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Collection::class,
            parameter: new ReflectionParameter(
                static function (Collection $collection): void {
                },
                'collection',
            ),
        );

        $this->assertSame(Collection::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame('mydb', $definition->getArgument(0));
        $this->assertSame('test', $definition->getArgument(1));
        $this->assertSame(['foo' => 'bar'], $definition->getArgument(2));
    }

    public function testWithoutCollection(): void
    {
        $autowire = new AutowireCollection(
            database: 'mydb',
            client: 'default',
            options: ['foo' => 'bar'],
        );

        $this->assertEquals([new Reference('mongodb.client.default'), 'selectCollection'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Collection::class,
            parameter: new ReflectionParameter(
                static function (Collection $priceReports): void {
                },
                'priceReports',
            ),
        );

        $this->assertSame(Collection::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame('mydb', $definition->getArgument(0));
        $this->assertSame('priceReports', $definition->getArgument(1));
        $this->assertSame(['foo' => 'bar'], $definition->getArgument(2));
    }

    public function testWithCodecOption(): void
    {
        $autowire = new AutowireCollection(
            database: 'mydb',
            client: 'default',
            codec: '@my_codec',
            options: ['foo' => 'bar'],
        );

        $this->assertEquals([new Reference('mongodb.client.default'), 'selectCollection'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Collection::class,
            parameter: new ReflectionParameter(
                static function (Collection $priceReports): void {
                },
                'priceReports',
            ),
        );

        $this->assertSame(Collection::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame('mydb', $definition->getArgument(0));
        $this->assertSame('priceReports', $definition->getArgument(1));
        $this->assertEquals(['foo' => 'bar', 'codec' => new Reference('my_codec')], $definition->getArgument(2));
    }

    public function testWithCodecInstanceParameter(): void
    {
        $codec = new class implements DocumentCodec {
            use DecodeIfSupported;
            use EncodeIfSupported;

            public function canDecode($value): bool
            {
                return $value instanceof Document;
            }

            public function canEncode($value): bool
            {
                return $value instanceof Document;
            }

            public function decode($value): Document
            {
                return $value;
            }

            public function encode($value): Document
            {
                return $value;
            }
        };

        $autowire = new AutowireCollection(
            database: 'mydb',
            client: 'default',
            codec: $codec,
            options: ['foo' => 'bar'],
        );

        $this->assertEquals([new Reference('mongodb.client.default'), 'selectCollection'], $autowire->value);

        $definition = $autowire->buildDefinition(
            value: $autowire->value,
            type: Collection::class,
            parameter: new ReflectionParameter(
                static function (Collection $priceReports): void {
                },
                'priceReports',
            ),
        );

        $this->assertSame(Collection::class, $definition->getClass());
        $this->assertEquals($autowire->value, $definition->getFactory());
        $this->assertSame('mydb', $definition->getArgument(0));
        $this->assertSame('priceReports', $definition->getArgument(1));
        $this->assertEquals(['foo' => 'bar', 'codec' => $codec], $definition->getArgument(2));
    }
}
