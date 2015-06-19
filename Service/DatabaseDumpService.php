<?php


namespace Becklyn\DatabaseDumpBundle\Service;


use Becklyn\DatabaseDumpBundle\Entity\DatabaseConnection;
use Becklyn\DatabaseDumpBundle\Exception\NoDatabaseDumpersRegisteredException;
use Becklyn\DatabaseDumpBundle\Exception\InvalidConnectionTypeException;

class DatabaseDumpService
{
    /**
     * @var BaseDatabaseDumpService[]
     */
    private $databaseDumper;


    /**
     * Registers the given DatabaseDumpService as available for use
     *
     * @param BaseDatabaseDumpService $databaseDumper
     */
    public function addDatabaseDumper (BaseDatabaseDumpService $databaseDumper)
    {
        $this->databaseDumper[] = $databaseDumper;
    }


    /**
     * Backups the given DatabaseConnection.
     *
     * Iterates through all registered DatabaseDumpServices and uses the first
     * that indicates it can handle the given DatabaseConnection's database type.
     *
     * @param DatabaseConnection $databaseConnection
     *
     * @return array <string> An associative array containing a 'success' (bool) and 'error' (string) key
     *               that contain additional information whether the dump process succeeded
     *
     * @throws InvalidConnectionTypeException
     * @throws NoDatabaseDumpersRegisteredException
     */
    public function dump (DatabaseConnection $databaseConnection)
    {
        if (is_null($this->databaseDumper))
        {
            throw new NoDatabaseDumpersRegisteredException('Unable to execute dump() because there are no BaseDatabaseDumpServices registered.');
        }

        foreach ($this->databaseDumper as $databaseDumper)
        {
            if ($databaseDumper->canHandle($databaseConnection))
            {
                return $databaseDumper->dump($databaseConnection);
            }
        }

        throw new InvalidConnectionTypeException("Couldn't find a DatabaseDumpService that can handle {$databaseConnection->getType()} databases.");
    }


    /**
     * Configures the backup path for the given DatabaseConnection
     *
     * @param DatabaseConnection $databaseConnection
     * @param string             $backupPath
     *
     * @throws NoDatabaseDumpersRegisteredException
     */
    public function configureBackupPath (DatabaseConnection $databaseConnection, $backupPath)
    {
        if (is_null($this->databaseDumper))
        {
            throw new NoDatabaseDumpersRegisteredException('Unable to execute configureBackupPath() because there are no BaseDatabaseDumpServices registered.');
        }

        foreach ($this->databaseDumper as $databaseDumper)
        {
            if ($databaseDumper->canHandle($databaseConnection))
            {
                $databaseDumper->configureBackupPath($databaseConnection, $backupPath);
            }
        }
    }
}
