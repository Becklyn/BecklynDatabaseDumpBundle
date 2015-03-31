<?php

namespace Becklyn\MysqlDumpBundle\Service;


use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerAware;

class DatabaseConfigurationService extends ContainerAware
{
    /**
     * The MysqlDumpBundle Configuration (config.yml)
     *
     * @var array
     */
    private $configuration;

    /**
     * @var Registry
     */
    private $doctrine;


    /**
     * ConfigurationService constructor.
     *
     * @param Registry $doctrine
     * @param array    $configuration
     */
    public function __construct (Registry $doctrine, array $configuration)
    {
        $this->configuration = $configuration;
        $this->doctrine = $doctrine;
    }


    /**
     * Returns the configuration values for the given key
     *
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getConfig ($key)
    {
        if (isset($this->configuration[$key]))
        {
            return $this->configuration[$key];
        }

        return null;
    }


    /**
     * Returns all database connections that should be backed up by searching the configuration:
     *  1) Uses, if provided, the arguments passed via CLI
     *  2) Searches the config.yml for the key 'becklyn_mysql_dump:databases'
     *  3) Searches the config.yml for the connections set up under 'doctrine:dbal:connections'
     *
     * @param array $cliArguments
     *
     * @return Connection[]
     */
    public function getDatabases ($cliArguments)
    {
        // If any command line arguments are passed we prefer those ...
        if (!empty($cliArguments))
        {
            return $this->getConnectionDataByConnectionNames($cliArguments);
        }

        // ... alternatively fall back to the previously configured databases ...
        if (!empty($databases = $this->getConfiguredDatabases()))
        {
            return $databases;
        }

        // ... or fallback to the full list of associated databases configured in Symfony.
        return $this->getAppDatabases();
    }


    /**
     * Returns a list of all databases that are configured under 'becklyn_mysql_dump:databases'
     *
     * @return array An array of connection name => connection
     */
    protected function getConfiguredDatabases ()
    {
        return $this->getConnectionDataByConnectionNames($this->getConfig('connections'));
    }


    /**
     * Returns all databases that are associated with this app.
     * See {@link http://symfony.com/doc/current/cookbook/doctrine/multiple_entity_managers.html}
     *
     * @return array An array of connection name => connection
     */
    protected function getAppDatabases ()
    {
        return $this->getConnectionDataByConnectionNames([]);
    }


    /**
     * Looks up the connection data for the given connection names
     *
     * @param array $connectionNames Looks up the given connection names. If empty all connections will be included.
     *
     * @return array An array of connection name => connection
     */
    protected function getConnectionDataByConnectionNames (array $connectionNames)
    {
        $connectionData = [];
        if (!empty($connectionNames))
        {
            // Create an associative array with connection name => null as fallback.
            // With each connection we can resolve we replace the value with the actual connection object
            $connectionData = array_combine($connectionNames, array_fill(0, count($connectionNames), null));
        }

        /** @var Connection $connection */
        foreach ($this->doctrine->getConnections() as $name => $connection)
        {
            // Add the current connection to the result if it either matches via name
            // or if the connectionNames array is empty, which means every connection will be included
            if (in_array($name, $connectionNames) || empty($connectionNames))
            {
                $connectionData[$name] = $connection;
            }
        }

        return $connectionData;
    }


    /**
     * Returns the backup directory from the config value 'becklyn_mysql_dump:directory'
     *
     * @param string $cliArgument
     *
     * @return string
     */
    public function getBackupDirectory ($cliArgument)
    {
        if (!empty($cliArgument))
        {
            return $cliArgument;
        }

        return $this->getConfig('directory');
    }
}
