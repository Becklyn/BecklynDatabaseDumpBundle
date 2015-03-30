<?php

namespace Becklyn\MysqlDumpBundle\DependencyInjection;

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

        $treeBuilder->root('becklyn_mysql_dump')
            ->children()
                ->arrayNode('databases')
                    ->canBeUnset()->prototype('scalar')->end()
                ->end()
                ->scalarNode('directory')
                    ->defaultValue('%kernel.root_dir%/var/db_backups/')
                ->end()
            ->end();

        return $treeBuilder;
    }
}
