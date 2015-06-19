<?php

namespace Becklyn\DatabaseDumpBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * @inheritdoc
 */
class BecklynDatabaseDumpExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');

        // Add the actual MySQL Dump configuration to the ConfigurationService
        $definition = $container->getDefinition('becklyn.db_dump.configuration');
        $definition->replaceArgument(1, $config['connections']);
        $definition->replaceArgument(2, $config['directory']);
        $definition->replaceArgument(3, $config['dumper']);
        $definition->replaceArgument(4, $config['profiles']);
    }
}
