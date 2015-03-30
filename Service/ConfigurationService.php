<?php

namespace Becklyn\MysqlDumpBundle\Service;


class ConfigurationService
{
    /**
     * The DumperBundle Configuration
     *
     * @var array
     */
    private $configuration;


    /**
     * ConfigurationService constructor.
     *
     * @param $configuration
     */
    public function __construct ($configuration)
    {
        $this->configuration = $configuration;
    }


    /**
     * Returns the configuration values for the given key
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function getConfig ($key)
    {
        if (isset($this->configuration[$key]))
        {
            return $this->configuration[$key];
        }

        return null;
    }
}
