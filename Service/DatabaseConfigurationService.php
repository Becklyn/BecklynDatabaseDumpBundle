<?php

namespace Becklyn\DatabaseDumpBundle\Service;


use Becklyn\DatabaseDumpBundle\Entity\DatabaseConnection;
use Doctrine\Bundle\DoctrineBundle\Registry;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\ContainerAware;

class DatabaseConfigurationService extends ContainerAware
{
    /**
     * @var Registry
     */
    private $doctrine;


    /**
     * @var string[]
     */
    private $connectionIdentifiers;


    /**
     * @var string
     */
    private $backupPath;


    /**
     * @var array
     */
    private $dumpServicesConfig;


    /**
     * @var string[]
     */
    private $profiles;


    /**
     * ConfigurationService constructor.
     *
     * @param Registry $doctrine
     * @param string[] $connectionIdentifiers
     * @param string   $backupPath
     * @param array    $dumpServicesConfig
     * @param string[] $profiles
     */
    public function __construct (Registry $doctrine, array $connectionIdentifiers, $backupPath, array $dumpServicesConfig, array $profiles)
    {
        $this->doctrine              = $doctrine;
        $this->connectionIdentifiers = $connectionIdentifiers;
        $this->backupPath            = !empty($backupPath) ? $backupPath : '';
        $this->dumpServicesConfig    = $dumpServicesConfig;
        $this->profiles              = $profiles;
    }


    /**
     * Returns a profile configuration
     *
     * @param string $profile
     *
     * @return array|null
     */
    public function getProfileConfiguration ($profile)
    {
        if (isset($this->profiles[$profile]))
        {
            return $this->profiles[$profile];
        }

        return null;
    }


    /**
     * Returns all database connections that should be backed up by searching the configuration:
     *  1) Uses, if provided, the arguments passed via CLI
     *  2) Searches the config.yml for the key 'becklyn_mysql_dump.databases'
     *  3) Uses all database connections as configured for doctrine.
     *
     * @param array $cliArguments
     *
     * @return array<DatabaseConnection|null> An associative array of identifier => DatabaseConnection
     */
    public function getDatabases ($cliArguments)
    {
        // If any command line arguments are passed we prefer those ...
        if (!empty($cliArguments))
        {
            return $this->getConnectionDataByIdentifier($cliArguments);
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
     * Returns a list of all databases that are configured under 'becklyn_mysql_dump.databases'
     *
     * @return array<DatabaseConnection|null> An associative array of identifier => DatabaseConnection
     */
    protected function getConfiguredDatabases ()
    {
        return $this->getConnectionDataByIdentifier($this->connectionIdentifiers);
    }


    /**
     * Returns all databases that are associated with this app.
     * See {@link http://symfony.com/doc/current/cookbook/doctrine/multiple_entity_managers.html}
     *
     * @return array<DatabaseConnection|null> An associative array of identifier => DatabaseConnection
     */
    protected function getAppDatabases ()
    {
        return $this->getConnectionDataByIdentifier([]);
    }


    /**
     * Searches for the DatabaseConnections based on the given connection identifiers
     *
     * @param array $identifiers A filter of connection identifier which will be returned. If empty all connections will be returned.
     *
     * @return array<DatabaseConnection|null> An associative array of identifier => DatabaseConnection
     */
    protected function getConnectionDataByIdentifier (array $identifiers)
    {
        $connections = [];

        if (!empty($identifiers))
        {
            // Create an associative array with identifier => null as fallback.
            // With each connection we can resolve we replace the value with the actual connection object
            $connections = array_combine($identifiers, array_fill(0, count($identifiers), null));
        }

        /**
         * @var string     $identifier
         * @var Connection $connection
         */
        foreach ($this->doctrine->getConnections() as $identifier => $connection)
        {
            // Add the current connection to the result if it either matches via name
            // or if the connectionNames array is empty, which means every connection will be included
            if (in_array($identifier, $identifiers) || empty($identifiers))
            {
                $connections[$identifier] = new DatabaseConnection($identifier, $connection);
            }
        }

        return $connections;
    }


    /**
     * Returns the backup directory from the config value 'becklyn_mysql_dump.directory'
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

        return $this->backupPath;
    }


    /**
     * Returns the configuration options for the given DatabaseDumpService identifier
     *
     * @param string $identifier
     *
     * @return array
     */
    public function getDumpServiceConfiguration ($identifier)
    {
        if (!isset($this->dumpServicesConfig[$identifier]))
        {
            return [];
        }

        return $this->dumpServicesConfig[$identifier];
    }
}
