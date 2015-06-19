<?php

namespace Becklyn\DatabaseDumpBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();

        $directoryDefault = '%kernel.root_dir%/var/db_backups/';

        $treeBuilder->root('becklyn_database_dump')
            ->children()
                ->arrayNode('connections')
                    ->requiresAtLeastOneElement()->canBeUnset()->prototype('scalar')->end()
                ->end()
                ->scalarNode('directory')
                    ->defaultValue($directoryDefault)
                ->end()
                ->arrayNode('profiles')
                    ->prototype('array')
                        ->children()
                            ->arrayNode('connections')
                                ->requiresAtLeastOneElement()->canBeUnset()->prototype('scalar')->end()
                            ->end()
                            ->scalarNode('directory')
                                ->defaultValue($directoryDefault)
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('dumper')
                    ->prototype('array')
                        ->prototype('scalar')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
