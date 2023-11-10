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
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Autowires a MongoDB client by using the id specified in config/packages/mongodb.yaml.
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class AutowireClient extends Autowire
{
    public function __construct(
        ?string $client = null,
        bool|string $lazy = false,
    ) {
        parent::__construct(
            service: $client === null ? Client::class : MongoDBExtension::createClientServiceId($client),
            lazy: $lazy,
        );
    }
}
