<?php

namespace Becklyn\MysqlDumpBundle\Service;


use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class MysqlDumpService
{

    /**
     * Performs the actual backup operation for the given database
     *
     * @param OutputInterface $output
     * @param string          $connectionName
     * @param Connection      $connection
     * @param string          $backupPath
     *
     * @return bool
     */
    public function dump (OutputInterface $output, $connectionName, Connection $connection, $backupPath)
    {
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists(dirname($backupPath)))
        {
            $fileSystem->mkdir(dirname($backupPath));

            // Check whether the directory could have been created
            if (!$fileSystem->exists(dirname($backupPath)))
            {
                throw new AccessDeniedException("Permission denied. Could not create directory {$backupPath}");
            }
        }

        $output->write("<info>»</info> Dumping <info>{$connection->getDatabase()}</info> (connection: <info>$connectionName</info>)... ");

        $dumpCommand = sprintf(
            'mysqldump --user="%s" --password="%s" --host="%s" --lock-all-tables "%s" | gzip > "%s"',
            $connection->getUsername(),
            $connection->getPassword(),
            $connection->getHost(),
            $connection->getDatabase(),
            $backupPath
        );

        $process = new Process($dumpCommand);
        $process->run();


        // We need to determine ourselves whether mysqldump has raised an error
        // as its return code is unreliable due to the fact that it's always returning 0
        $errorOut = trim($process->getErrorOutput());
        $success  = (preg_match('/Got error:.*/', $errorOut) === 0);

        if ($success)
        {
            $output->writeln('<info>done</info>');
        }
        else
        {
            $fileSystem->remove($backupPath);
            $output->writeln('<error>failed</error>');
        }

        // Print the stdError contents after the status flag for a nicely formatted output
        if (!$success || $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
        {
            $error = preg_replace("~^(.*?)$~m", "    \\1", $errorOut);
            $output->writeln($error);
        }

        return $success;
    }
}
