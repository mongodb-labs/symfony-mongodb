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
use MongoDB\Codec\DocumentCodec;
use MongoDB\Collection;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;
use function ltrim;
use function sprintf;

/**
 * Autowires a MongoDB collection for a specific database.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class AutowireCollection extends AutowireCallable
{
    private readonly string $serviceId;

    public function __construct(
        private readonly ?string $collection = null,
        private readonly ?string $database = null,
        ?string $client = null,
        private readonly string|DocumentCodec|null $codec = null,
        private readonly array $options = [],
        bool|string $lazy = false,
    ) {
        $this->serviceId = $client === null
            ? Client::class
            : MongoDBExtension::createClientServiceId($client);

        parent::__construct(
            callable: [new Reference($this->serviceId), 'selectCollection'],
            lazy: $lazy,
        );
    }

    public function buildDefinition(mixed $value, ?string $type, ReflectionParameter $parameter): Definition
    {
        $options = $this->options;
        if ($this->codec !== null) {
            $options['codec'] = $this->codec;
        }

        if (isset($options['codec']) && is_string($options['codec'])) {
            $options['codec'] = new Reference(ltrim($options['codec'], '@'));
        }

        return (new Definition(is_string($this->lazy) ? $this->lazy : ($type ?: Collection::class)))
            ->setFactory($value)
            ->setArguments([
                $this->database ?? sprintf('%%%s.default_database%%', $this->serviceId),
                $this->collection ?? $parameter->getName(),
                $options,
            ])
            ->setLazy($this->lazy);
    }
}
