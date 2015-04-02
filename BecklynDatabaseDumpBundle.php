<?php

namespace Becklyn\DatabaseDumpBundle;

use Becklyn\DatabaseDumpBundle\DependencyInjection\BecklynDatabaseDumpExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BecklynDatabaseDumpBundle extends Bundle
{
    public function getContainerExtension ()
    {
        return new BecklynDatabaseDumpExtension();
    }
}
