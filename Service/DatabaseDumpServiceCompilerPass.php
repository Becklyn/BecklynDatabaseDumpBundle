<?php


namespace Becklyn\DatabaseDUmpBundle\Service;


use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class DatabaseDumpServiceCompilerPass implements CompilerPassInterface
{
    const DATABASE_DUMPER_SERVICE_KEY = 'becklyn.db_dump.dump';


    /**
     * Scans all registered services for tagged DatabaseDumpServiceInterface implementations
     * and registers them with the DatabaseDumpService
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->has(self::DATABASE_DUMPER_SERVICE_KEY))
        {
            return;
        }

        // Get the definition of the DatabaseDumpService service itself
        $definition = $container->findDefinition(self::DATABASE_DUMPER_SERVICE_KEY);

        // Get all DatabaseDumpServiceInterface implementations that are tagged for the DatabaseDumpService
        foreach ($container->findTaggedServiceIds(self::DATABASE_DUMPER_SERVICE_KEY) as $serviceKey => $tags)
        {
            $definition->addMethodCall(
                'addDatabaseDumper',
                [
                    new Reference($serviceKey)
                ]
            );
        }
    }
}
