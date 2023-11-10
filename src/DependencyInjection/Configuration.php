<?php

declare(strict_types=1);

namespace MongoDB\Bundle\DependencyInjection;

use InvalidArgumentException;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

use function array_key_first;
use function count;
use function sprintf;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('mongodb');

        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('clients')
                ->requiresAtLeastOneElement()
                    ->useAttributeAsKey('id')
                    ->arrayPrototype()
                        ->beforeNormalization()
                        ->ifString()
                        ->then(static fn ($v) => ['uri' => $v])
                        ->end()
                        ->children()
                            ->scalarNode('uri')
                                ->info('MongoDB connection string')
                            ->end()
                            ->arrayNode('uriOptions')
                                ->info('Additional connection string options')
                                ->variablePrototype()->end()
                            ->end()
                            ->arrayNode('driverOptions')
                                ->info('Driver-specific options')
                                ->variablePrototype()->end()
                            ->end()
                            ->scalarNode('default_database')
                                ->info('The default database to use for this client.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('default_client')
                    ->info('The default client to use. Must be one of the clients defined in "clients".')
                ->end()
            ->end()
            ->validate()
            ->always(static function ($v) {
                if (count($v['clients']) === 1 && ! isset($v['default_client'])) {
                    $v['default_client'] = array_key_first($v['clients']);
                } elseif (! isset($v['default_client'])) {
                    throw new InvalidArgumentException('You must define a "default_client" when more than one client is defined.');
                }

                if (! isset($v['clients'][$v['default_client']])) {
                    throw new InvalidArgumentException(sprintf('The "default_client" "%s" is not defined in "clients" section.', $v['default_client']));
                }

                return $v;
            })
            ->end();

        return $treeBuilder;
    }
}
