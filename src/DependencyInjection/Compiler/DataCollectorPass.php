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

namespace MongoDB\Bundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/** @internal */
final class DataCollectorPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (! $container->has('profiler')) {
            return;
        }

        /**
         * Add a subscriber to each client to collect driver events.
         *
         * @see \MongoDB\Bundle\DataCollector\MongoDBDataCollector::configureClient()
         */
        foreach ($container->findTaggedServiceIds('mongodb.client', true) as $clientId => $attributes) {
            $container->getDefinition($clientId)->setConfigurator([new Reference('mongodb.data_collector'), 'configureClient']);
        }
    }
}
