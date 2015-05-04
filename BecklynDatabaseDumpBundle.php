<?php

namespace Becklyn\DatabaseDumpBundle;

use Becklyn\DatabaseDUmpBundle\Service\DatabaseDumpServiceCompilerPass;
use Becklyn\DatabaseDumpBundle\DependencyInjection\BecklynDatabaseDumpExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BecklynDatabaseDumpBundle extends Bundle
{
    public function build (ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new DatabaseDumpServiceCompilerPass());
    }


    public function getContainerExtension ()
    {
        return new BecklynDatabaseDumpExtension();
    }
}
