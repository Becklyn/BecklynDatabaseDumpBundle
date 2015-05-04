<?php


namespace Becklyn\DatabaseDumpBundle\Service;


use Becklyn\DatabaseDumpBundle\Entity\DatabaseConnection;

abstract class BaseDatabaseDumpService
{
    /**
     * @var array
     */
    protected $config;


    /**
     * MysqlDumpService constructor.
     *
     * @param DatabaseConfigurationService $configurationService
     */
    public function __construct (DatabaseConfigurationService $configurationService)
    {
        $this->config = $configurationService->getDumpServiceConfiguration($this->getIdentifier());
    }


    /**
     * Returns a unique identifier for this DatabaseDumpService implementation
     * which will also be used inside the config.yml to specifically configure
     * this service
     *
     * @return string
     */
    public abstract function getIdentifier ();

    /**
     * Performs the actual backup operation for the given MySQL DatabaseConnection
     *
     * @param DatabaseConnection $connection
     *
     * @return array
     */
    public abstract function dump (DatabaseConnection $connection);


    /**
     * Sets or updates, if necessary, the backup file path for the given connection
     *
     * @param DatabaseConnection $connection
     * @param string             $backupPath
     */
    public abstract function configureBackupPath (DatabaseConnection $connection, $backupPath);


    /**
     * Determines whether or not this DatabaseDumpService can backup the given DatabaseConnection
     *
     * @param DatabaseConnection $connection
     *
     * @return bool
     */
    public abstract function canHandle (DatabaseConnection $connection);


    /**
     * Determines whether the given connection is valid
     *
     * @param DatabaseConnection $connection
     *
     * @return bool
     */
    public abstract function validateConnection (DatabaseConnection $connection);


    /**
     * Retrieves the config value by key
     *
     * @param string $key
     *
     * @return mixed|null
     */
    protected function getConfigValue ($key)
    {
        if (!isset($this->config[$key]))
        {
            return null;
        }

        return $this->config[$key];
    }
}
