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
use MongoDB\Database;
use MongoDB\Driver\ReadConcern;
use MongoDB\Driver\ReadPreference;
use MongoDB\Driver\WriteConcern;
use ReflectionParameter;
use Symfony\Component\DependencyInjection\Attribute\AutowireCallable;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

use function is_string;
use function sprintf;

/**
 * Autowires a MongoDB database.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class AutowireDatabase extends AutowireCallable
{
    private readonly string $serviceId;

    public function __construct(
        private readonly ?string $database = null,
        ?string $client = null,
        private readonly string|DocumentCodec|null $codec = null,
        private readonly string|array|null $typeMap = null,
        private readonly string|ReadPreference|null $readPreference = null,
        private readonly string|WriteConcern|null $writeConcern = null,
        private readonly string|ReadConcern|null $readConcern = null,
        bool|string $lazy = false,
    ) {
        $this->serviceId = $client === null
            ? Client::class
            : MongoDBExtension::createClientServiceId($client);

        parent::__construct(
            callable: [new Reference($this->serviceId), 'selectDatabase'],
            lazy: $lazy,
        );
    }

    public function buildDefinition(mixed $value, ?string $type, ReflectionParameter $parameter): Definition
    {
        $options = [];
        foreach (['codec', 'typeMap', 'readPreference', 'writeConcern', 'readConcern'] as $option) {
            $optionValue = $this->$option;
            if ($optionValue === null) {
                continue;
            }

            // If a string was given, it may be a service ID or parameter. Handle it accordingly
            if (is_string($optionValue)) {
                $optionValue = $option === 'typeMap' ? sprintf('%%%s%%', $optionValue) : new Reference($optionValue);
            }

            $options[$option] = $optionValue;
        }

        return (new Definition(is_string($this->lazy) ? $this->lazy : ($type ?: Database::class)))
            ->setFactory($value)
            ->setArguments([
                $this->database ?? sprintf('%%%s.default_database%%', $this->serviceId),
                $options,
            ])
            ->setLazy($this->lazy);
    }
}
