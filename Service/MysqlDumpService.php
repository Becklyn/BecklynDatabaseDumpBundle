<?php

namespace Becklyn\DatabaseDumpBundle\Service;


use Becklyn\DatabaseDumpBundle\Entity\DatabaseConnection;
use Becklyn\DatabaseDumpBundle\Exception\BackupDeletionException;
use Becklyn\DatabaseDumpBundle\Exception\DirectoryCreationException;
use Becklyn\DatabaseDumpBundle\Exception\InvalidConnectionException;
use Becklyn\DatabaseDumpBundle\Exception\InvalidConnectionTypeException;
use Becklyn\DatabaseDumpBundle\Exception\NullConnectionException;
use mysqli;
use mysqli_driver;
use mysqli_sql_exception;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

class MysqlDumpService extends BaseDatabaseDumpService
{
    /**
     * Determines whether or not usage of GZip is by default enabled when explicit Dumper configuration is missing
     */
    const OPTION_DEFAULT_GZIP = true;

    /**
     * Returns a unique identifier for this DatabaseDumpService implementation
     * which will also be used inside the config.yml to specifically configure
     * this service
     *
     * @return string
     */
    public function getIdentifier ()
    {
        return 'becklyn_mysql';
    }


    /**
     * Performs the actual backup operation for the given MySQL DatabaseConnection
     *
     * @param DatabaseConnection $connection
     *
     * @return array
     *
     * @throws DirectoryCreationException
     * @throws InvalidConnectionException
     * @throws InvalidConnectionTypeException
     * @throws NullConnectionException
     * @throws BackupDeletionException
     */
    public function dump (DatabaseConnection $connection)
    {
        if (is_null($connection))
        {
            throw new NullConnectionException("The connection can't be null.");
        }

        if (!$this->canHandle($connection))
        {
            throw new InvalidConnectionTypeException("Can't perform backup on non-MySQL/MariaDB Databases.");
        }

        if (!$this->validateConnection($connection))
        {
            throw new InvalidConnectionException("Could not establish connection to the given connection '{$connection->getIdentifier()}'.");
        }

        if (!$this->createBackupDirectory($connection->getBackupPath()))
        {
            throw new DirectoryCreationException("Permission denied. Could not create directory {$connection->getBackupPath()}");
        }

        $process = new Process($this->getBackupCommand($connection));
        $process->run();

        // We need to determine ourselves whether mysqldump has raised an error
        // as its return code is unreliable due to the fact that it's always returning 0
        $errorOut = trim($process->getErrorOutput());
        $success  = (stripos($errorOut, "Got error:") === false);

        if (!$success && !$this->removeFaultyBackup($connection->getBackupPath()))
        {
            throw new BackupDeletionException(
                "Could not remove faulty backup of database '{$connection->getDatabase()}' (connection: {$connection->getIdentifier()}) at {$connection->getBackupPath()}.");
        }

        return [
            'success' => $success,
            'error'   => $errorOut
        ];
    }


    /**
     * Creates the executing command based on the current configuration
     * for the given DatabaseConnection
     *
     * @param DatabaseConnection $connection
     *
     * @return string
     */
    protected function getBackupCommand (DatabaseConnection $connection)
    {
        $command = sprintf(
            'mysqldump --user="%s" --password="%s" --host="%s" --lock-all-tables "%s"',
            $connection->getUsername(),
            $connection->getPassword(),
            $connection->getHost(),
            $connection->getDatabase()
        );

        // If GZip is enabled we need to pipe it through gzip and change the file extension
        if ($this->getConfigValue('gzip', self::OPTION_DEFAULT_GZIP))
        {
            $command .= sprintf(' | gzip > "%s"', $connection->getBackupPath());
        }
        else
        {
            $command .= sprintf(' > "%s"', $connection->getBackupPath());
        }

        return $command;
    }


    /**
     * Sets or updates, if necessary, the backup file path for the given connection
     *
     * @param DatabaseConnection $connection
     * @param string             $backupPath
     */
    public function configureBackupPath (DatabaseConnection $connection, $backupPath)
    {
        // Append the .gz file extension if GZip is enabled
        if ($this->getConfigValue('gzip', self::OPTION_DEFAULT_GZIP))
        {
            $backupPath .= '.gz';
        }

        $connection->setBackupPath($backupPath);
    }


    /**
     * Determines whether or not this DatabaseDumpService can backup the given DatabaseConnection
     *
     * @param DatabaseConnection $connection
     *
     * @return bool
     */
    public function canHandle (DatabaseConnection $connection)
    {
        return ($connection->getType() === DatabaseConnection::TYPE_MYSQL);
    }


    /**
     * Determines whether the given connection is valid
     *
     * @param DatabaseConnection $connection
     *
     * @return bool
     */
    public function validateConnection (DatabaseConnection $connection)
    {
        try
        {
            $driver = new mysqli_driver();
            // Backup the current configuration to prevent any side effects our reporting mode may cause
            $originalReportMode = $driver->report_mode;

            // Ensure that MySQLi is throwing proper exceptions
            $driver->report_mode = MYSQLI_REPORT_ALL;

            $mysqli = new mysqli($connection->getHost(), $connection->getUsername(), $connection->getPassword(), $connection->getDatabase(), $connection->getPort());

            // Ping the database to check whether or not we're successfully connected
            if (!$mysqli->ping())
            {
                return false;
            }

            // Revert the report_mode back to it's original value
            $driver->report_mode = $originalReportMode;

            $mysqli->close();

            return true;
        }
        catch (mysqli_sql_exception $e)
        {
            return false;
        }
    }


    /**
     * Creates the given backup directory
     *
     * @param string $backupPath
     *
     * @return bool
     */
    protected function createBackupDirectory ($backupPath)
    {
        try
        {
            $fileSystem = new Filesystem();
            if ($fileSystem->exists(dirname($backupPath)))
            {
                return true;
            }

            $fileSystem->mkdir(dirname($backupPath));

            return true;
        }
        catch (IOException $e)
        {
            // Gracefully swallow all exceptions. We don't intent to return the exception back to the caller
            return false;
        }
    }


    /**
     * Removes the faulty backup
     *
     * @param string $backupFilePath
     *
     * @return bool
     */
    protected function removeFaultyBackup ($backupFilePath)
    {
        try
        {
            $fileSystem = new Filesystem();
            $fileSystem->remove($backupFilePath);

            return true;
        }
        catch (IOException $e)
        {
            // Gracefully swallow all exceptions. We don't intent to return the exception back to the caller
            return false;
        }
    }
}
