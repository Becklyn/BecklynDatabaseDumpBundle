<?php

namespace Becklyn\MysqlDumpBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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
            ->setDescription("Dumps the configured or given databases using 'mysqldump' to .sql files.")
            ->addOption(
                'connections',
                'c',
                InputOption::VALUE_REQUIRED,
                'Dumps the provided database connections. Multiple connection names are separated by <info>comma (,)</info>',
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
                'force',
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
        $dumper       = $this->getContainer()->get('becklyn_mysql_dump.services.dump');
        $dumperConfig = $this->getContainer()->get('becklyn_mysql_dump.services.configuration');

        $connections = $dumperConfig->getDatabases($input->getOption('connections'));
        $backupPath  = $dumperConfig->getBackupDirectory($input->getOption('path'));

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        // Print headline
        $headline = $formatter->formatBlock(array('', '  Becklyn MySQL Database Dumper', ''), 'comment');
        $output->writeln($headline);

        // If there are no database connection information available we can't dump anything
        if (empty($connections))
        {
            $error = $formatter->formatBlock(array('', '  No connection data found.  ', ''), 'error');
            $output->writeln("\n{$error}\n");

            return 1;
        }

        $unresolvedConnections = false;
        $dbBackupPaths         = [];
        $tableRows             = [];

        /** @var Connection $connectionData */
        foreach ($connections as $connectionName => $connectionData)
        {
            // Check if the connection could not be resolved.
            if ($connectionData === null)
            {
                $unresolvedConnections = true;

                $tableRows[] = [
                    '<error>- unresolved -</error>', $connectionName, '-'
                ];

                continue;
            }

            // Preserve the designated file names for the actual backup process as an actual dump
            // may take very long the actual file names would differ from the one we printed to the user
            $dbBackupPaths[$connectionName] = $this->getBackupFilePath($backupPath, $connectionName, $connectionData);

            $tableRows[] = [
                $connectionData->getDatabase(),
                $connectionName,
                $dbBackupPaths[$connectionName]
            ];
        }

        // Print a nice table with the database name and the actual target file path
        $this->getHelper('table')
             ->setHeaders(array('Database', 'Connection', 'Backup file'))
             ->setRows($tableRows)
             ->render($output);

        // Check if any connections could not be resolved and then print an error to abort.
        if ($unresolvedConnections)
        {
            $error = $formatter->formatBlock(array('', 'Could not resolve one or more connections.', ''), 'error');
            $output->writeln("\n{$error}\n");

            return 1;
        }


        if (!$input->getOption('force'))
        {
            $output->writeln('');
            $question = new ConfirmationQuestion('<question>Would you like to backup now? [Yn]</question>', true);

            if (!$this->getHelper('question')->ask($input, $output, $question))
            {
                $output->writeln('<info>Exiting</info>. No files were written.');

                return 0;
            }
        }

        $output->writeln('');

        try
        {
            // Finally dump all databases
            foreach ($connections as $connectionName => $connectionData)
            {
                $dumper->dump($output, $connectionName, $connectionData, $dbBackupPaths[$connectionName]);
            }
        }
        catch (AccessDeniedException $e)
        {
            $error = $formatter->formatBlock(
                [
                    '',
                    '  An error occurred during backup creation:  ',
                    "  {$e->getMessage()} ",
                    ''
                ],
                'error'
            );
            $output->writeln($error);
        }

        $output->writeln("\n<info>»» Backup completed.</info>");

        return 0;
    }


    /**
     * Returns the backup file path for the given connection
     *
     * @param string     $backupPath
     * @param string     $connectionName
     * @param Connection $connection
     *
     * @return string
     */
    protected function getBackupFilePath ($backupPath, $connectionName, Connection $connection)
    {
        $rootDir = dirname($this->getContainer()->get('kernel')->getRootDir());

        // When the backup path is inside the root dir we convert the path to a relative path to shorten the output
        if (strpos($backupPath, $rootDir) === 0)
        {
            $backupPath = str_replace($rootDir, '.', $backupPath);
        }

        return sprintf('%s/%s_backup_%s__%s.sql.gz', rtrim($backupPath, '/'), date('Y-m-d_H-i'), $connectionName, $connection->getDatabase());
    }
}
