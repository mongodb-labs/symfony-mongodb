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

namespace MongoDB\Bundle\DependencyInjection;

use InvalidArgumentException;
use MongoDB\Client;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;

use function dirname;
use function sprintf;
use function trim;

final class MongoDBExtension extends Extension
{
    public function getAlias(): string
    {
        return 'mongodb';
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new PhpFileLoader($container, new FileLocator(dirname(__DIR__, 2) . '/config'));
        $loader->load('services.php');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $this->createClients($config['default_client'], $config['clients'], $container);
    }

    /** @internal */
    public static function createClientServiceId(string $clientId): string
    {
        if (trim($clientId) === '') {
            throw new InvalidArgumentException('The client id cannot be empty.');
        }

        return sprintf('mongodb.client.%s', $clientId);
    }

    private function createClients(string $defaultClient, array $clients, ContainerBuilder $container): void
    {
        $clientPrototype = $container->getDefinition('mongodb.prototype.client');

        foreach ($clients as $client => $configuration) {
            $serviceId = self::createClientServiceId($client);

            $clientDefinition = clone $clientPrototype;
            $clientDefinition->setArgument('$uri', $configuration['uri']);
            $clientDefinition->setArgument('$uriOptions', $configuration['uri_options'] ?? []);
            $clientDefinition->setArgument('$driverOptions', $configuration['driver_options'] ?? []);

            $container->setDefinition($serviceId, $clientDefinition);

            if (isset($configuration['default_database'])) {
                $container->setParameter(sprintf('%s.default_database', $serviceId), $configuration['default_database']);
            }

            // Allows to autowire the client using the name
            $container->registerAliasForArgument($serviceId, Client::class, sprintf('%sClient', $client));
        }

        // Register an autowiring alias for the default client
        $container->setAlias(Client::class, self::createClientServiceId($defaultClient));

        if (isset($clients[$defaultClient]['default_database'])) {
            $container->setParameter(
                sprintf('%s.default_database', Client::class),
                $clients[$defaultClient]['default_database'],
            );
        }

        // Remove the prototype definition as it's tagged as client
        $container->removeDefinition('mongodb.prototype.client');
    }
}
