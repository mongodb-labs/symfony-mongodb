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

namespace MongoDB\Bundle\Attribute;

use Attribute;
use MongoDB\Bundle\DependencyInjection\MongoDBExtension;
use MongoDB\Client;
use MongoDB\Database;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;

/**
 * Autowires a MongoDB database.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class AutowireDatabase extends AutowireCallable
{
    public function __construct(
        private readonly ?string $database = null,
        ?string $client = null,
        private readonly array $options = [],
        bool|string $lazy = false,
    ) {
        $callable = $client === null
            ? [new Reference(Client::class), 'selectDatabase']
            : [new Reference(MongoDBExtension::createClientServiceId($client)), 'selectDatabase'];

        parent::__construct(
            callable: $callable,
            lazy: $lazy,
        );
    }

    public function buildDefinition(mixed $value, ?string $type, ReflectionParameter $parameter): Definition
    {
        return (new Definition(is_string($this->lazy) ? $this->lazy : ($type ?: Database::class)))
            ->setFactory($value)
            ->setArguments([$this->database ?? $parameter->getName(), $this->options])
            ->setLazy($this->lazy);
    }
}
