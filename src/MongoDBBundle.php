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

namespace MongoDB\Bundle;

use MongoDB\Bundle\Caster\MongoDBCaster;
use MongoDB\Bundle\DependencyInjection\Compiler\DataCollectorPass;
use MongoDB\Bundle\DependencyInjection\MongoDBExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Symfony\Component\VarDumper\Cloner\AbstractCloner;

use function class_exists;
use function method_exists;

final class MongoDBBundle extends AbstractBundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        return new MongoDBExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new DataCollectorPass());
    }

    public function boot(): void
    {
        // The VarDumper component is optional, it is not installed in production.
        if (! class_exists(AbstractCloner::class)) {
            return;
        }

        $casters = MongoDBCaster::getCasters();

        if (method_exists(AbstractCloner::class, 'addDefaultCasters')) {
            AbstractCloner::addDefaultCasters($casters);
        } else {
            // Compatibility with Symfony < 7.4
            AbstractCloner::$defaultCasters = [...AbstractCloner::$defaultCasters, ...$casters];
        }
    }
}
