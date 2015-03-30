<?php

namespace Becklyn\MysqlDumpBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Handles the importing of the images from the command line
 */
class DumpCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure ()
    {
        $this
            ->setName('becklyn:db:dump')
            ->setDescription('Dumps the configured or given Databases using \'mysqldump\' to .sql files.')
            ->addOption(
                'databases',
                'd',
                InputOption::VALUE_REQUIRED,
                'If provided it overrides the values from the <info>config.yml</info>. Multiple Database names are separated by <info>comma (,)</info>',
                null
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                'The folder path where the .sql file will be saved. Defaults to %kernel.root%/var/db_backups/ if not specified otherwise.',
                null
            )
            ->addOption(
                'yes',
                null,
                InputOption::VALUE_NONE,
                'Suppresses prompt asking whether to continue.'
            );
    }


    /**
     * {@inheritdoc}
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute (InputInterface $input, OutputInterface $output)
    {
        $selectedDatabases = $input->getOption('databases');
        $backupPath        = $input->getOption('path');
        $suppressPrompt    = $input->getOption('yes');

        $databases = null;


        // When the user has specified any databases (comma-separated) as parameter
        // we use those instead of the ones that were configured in the config.yml
        if ($selectedDatabases !== null)
        {
            $databases = $this->getConnectionDataByDatabaseNames(explode(',', $selectedDatabases));
        }
        else
        {
            // Use the default, configured databases from the config.yml:
            // 1) Use 'becklyn_mysqldump:databases' when set
            // 2) else fallback to the databases from 'doctrine:dbal:connections'
            $databases = $this->getDatabases();
        }

        if ($backupPath === null)
        {
            $backupPath = $this->getBackupDirectory();
        }

        // If there are no database connection information available we can't dump anything
        if (count($databases) === 0)
        {
            $output->writeln('<error>Exiting</error>: No connection data found.');
            return;
        }

        $output->writeln('<info>»</info> Found connection data for the following databases:');
        $output->writeln('Database(s): <info>' . implode(',', array_keys($databases)) . '</info>');
        $output->writeln("Backup directory: <info>$backupPath</info>");

        if (!$suppressPrompt)
        {
            $output->writeln('');

            if (!$this->getHelperSet()->get('dialog')->askConfirmation($output, '<question>Would you like to backup now? [Yn]</question>'))
            {
                $output->writeln('<info>Exiting</info>. No files were written.');
                return;
            }
        }

        $output->writeln('');

        foreach ($databases as $database => $connectionData)
        {
            $this->backupDatabase($output, $database, $connectionData['host'], $connectionData['username'], $connectionData['password'], $backupPath);
        }

        $output->writeln("\n<info>»» Backup completed.</info>");
    }


    /**
     * Performs the actual backup operation for the given database
     *
     * @param OutputInterface $output
     * @param string          $database
     * @param string          $host
     * @param string          $username
     * @param string          $password
     * @param string          $backupPath
     *
     * @return bool
     */
    protected function backupDatabase (OutputInterface $output, $database, $host, $username, $password, $backupPath)
    {
        $fileSystem = new Filesystem();
        if (!$fileSystem->exists($backupPath))
        {
            $fileSystem->mkdir($backupPath);
        }

        $backupFilePath = sprintf('%s/%s_backup_%s.sql', rtrim($backupPath, '/'), date('Y-m-d_H-i'), $database);
        $output->write("<info>»</info> Dumping <info>$database</info> to <info>$backupFilePath</info>");

        $dumpCommand = sprintf('mysqldump -u%s -p%s -h %s -x --compact %s > %s', $username, $password, $host, $database, $backupFilePath);

        $process = new Process($dumpCommand);
        $process->run();

        $success = $process->isSuccessful();
        if ($success)
        {
            $output->writeln(' ...<info>done</info>');
        }
        else
        {
            $fileSystem->remove($backupFilePath);
            $output->writeln(' ...<error>failed</error>');
        }

        return $success;
    }


    /**
     * Returns all databases that will be backed up by searching the configuration:
     *  1) Searches the config.yml for the key 'becklyn_mysql_dump:databases'
     *  2) Searches the config.yml for the connections set up under 'doctrine:dbal:connections'
     *
     * @return array
     */
    protected function getDatabases ()
    {
        $databases = $this->getConfiguredDatabases();

        if (count($databases) === 0)
        {
            $databases = $this->getAppDatabases();
        }

        return $databases;
    }


    /**
     * Returns a list of all databases that are configured under becklyn_mysql_dump_databases:
     *
     * @return array
     */
    protected function getConfiguredDatabases ()
    {
        $databases = $this->getContainer()->get('becklyn_mysql_dump.configuration')->getConfig('databases');

        return $this->getConnectionDataByDatabaseNames($databases);
    }


    /**
     * Returns all databases that are associated with this app.
     * See {@link http://symfony.com/doc/current/cookbook/doctrine/multiple_entity_managers.html}
     *
     * @return array
     */
    protected function getAppDatabases ()
    {
        $databaseNames = [];
        $doctrine  = $this->getContainer()->get('doctrine');

        /** @var \Doctrine\DBAL\Connection $connection */
        foreach ($doctrine->getConnections() as $connection)
        {
            $databaseNames[] = $connection->getDatabase();
        }

        return $this->getConnectionDataByDatabaseNames($databaseNames);
    }


    /**
     * Looks up the connection data for the given database names
     *
     * @param array $databaseNames
     *
     * @return array
     */
    protected function getConnectionDataByDatabaseNames (array $databaseNames)
    {
        $connectionData = [];
        $doctrine = $this->getContainer()->get('doctrine');

        /** @var \Doctrine\DBAL\Connection $connection */
        foreach ($doctrine->getConnections() as $connection)
        {
            if (in_array($connection->getDatabase(), $databaseNames))
            {
                $connectionData[$connection->getDatabase()] = [
                    'host'     => $connection->getHost(),
                    'username' => $connection->getUsername(),
                    'password' => $connection->getPassword()
                ];
            }
        }

        return $connectionData;
    }


    /**
     * Returns the backup directory from the config value 'becklyn_mysql_dump:directory'
     *
     * @return string
     */
    protected function getBackupDirectory ()
    {
        return $this->getContainer()->get('becklyn_mysql_dump.configuration')->getConfig('directory');
    }
}
