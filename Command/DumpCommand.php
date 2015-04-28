<?php

namespace Becklyn\DatabaseDumpBundle\Command;

use Becklyn\DatabaseDumpBundle\Entity\DatabaseConnection;
use Becklyn\DatabaseDumpBundle\Exception\MysqlDumpException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

/**
 * Dumps the given Databases via CLI
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
                'Dumps the provided database connections. Multiple connection identifiers are separated by <info>comma (,)</info>',
                null
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                "The folder path where the .sql file will be saved. Defaults to '%kernel.root%/var/db_backups/'.",
                null
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
        $dumper       = $this->getContainer()->get('becklyn.db_dump.dump');
        $dumperConfig = $this->getContainer()->get('becklyn.db_dump.configuration');

        $connections = $dumperConfig->getDatabases($input->getOption('connections'));
        $backupPath  = $dumperConfig->getBackupDirectory($input->getOption('path'));

        /** @var FormatterHelper $formatter */
        $formatter = $this->getHelper('formatter');

        // Print headline
        $output->writeln($formatter->formatBlock(['', '  Becklyn MySQL Database Dumper', ''], 'comment'));

        // If there are no database connection information available we can't dump anything
        if (empty($connections))
        {
            $error = $formatter->formatBlock(['', '  No connection data found.  ', ''], 'error');
            $output->writeln("\n{$error}\n");

            return 1;
        }

        $this->printConnectionOverviewTable($output, $connections, $backupPath);

        // Check if any connections could not be resolved and then print an error to abort.
        if (in_array(null, $connections, true))
        {
            $error = $formatter->formatBlock(['', 'Could not resolve one or more connections.', ''], 'error');
            $output->writeln("\n{$error}\n");

            return 1;
        }

        if (!$input->getOption('no-interaction'))
        {
            $output->writeln('');
            $question = new ConfirmationQuestion('<question>Would you like to start the backup now? [Yn]</question>', true);

            if (!$this->getHelper('question')->ask($input, $output, $question))
            {
                $output->writeln('<info>Aborting</info>. No files were written.');

                return 0;
            }
        }

        $output->writeln('');

        // Finally dump all databases
        /**
         * @var string             $identifier
         * @var DatabaseConnection $connection
         */
        foreach ($connections as $identifier => $connection)
        {
            $output->write("<info>»</info> Dumping <info>{$connection->getDatabase()}</info> (connection: <info>$identifier</info>)... ");

            try
            {
                $dumpResult = $dumper->dump($connection);

                if ($dumpResult['success'])
                {
                    $output->writeln('<info>done</info>');
                }
                else
                {
                    $output->writeln('<error>failed</error>');
                }

                // Print the stdError contents after the status flag for a nicely formatted output
                if (!$dumpResult['success'] || $output->getVerbosity() >= OutputInterface::VERBOSITY_VERBOSE)
                {
                    $output->writeln(preg_replace("~^(.*?)$~m", "    \\1", $dumpResult['error']));
                }
            }
            catch (MysqlDumpException $e)
            {
                $output->writeln('<error>failed</error>');
                $this->printDumpException($output, $e);
            }
        }

        $output->writeln("\n<info>»» Backup completed.</info>");

        return 0;
    }


    /**
     * Returns the backup file path for the given connection
     *
     * @param string $backupPath
     * @param string $identifier
     * @param string $database
     *
     * @return string
     */
    protected function getBackupFilePath ($backupPath, $identifier, $database)
    {
        $rootDir = dirname($this->getContainer()->get('kernel')->getRootDir());

        // When the backup path is inside the root dir we convert the path to a relative path to shorten the output
        if (strpos($backupPath, $rootDir) === 0)
        {
            $backupPath = str_replace($rootDir, '.', $backupPath);
        }

        return sprintf('%s/%s_backup_%s__%s.sql.gz', rtrim($backupPath, '/'), date('Y-m-d_H-i'), $identifier, $database);
    }


    /**
     * Prints an error box to the UI for the given exception
     *
     * @param OutputInterface    $output
     * @param MysqlDumpException $e
     */
    protected function printDumpException (OutputInterface $output, MysqlDumpException $e)
    {
        $error = $this->getHelper('formatter')->formatBlock(['', '  An error occurred during backup creation:  ', "  {$e->getMessage()}  ", ''], 'error');
        $output->writeln("\n$error\n");
    }


    /**
     * Renders the connection overview table that shows
     * - the associated database name
     * - the connection's identifier
     * - the backup file path
     *
     * @param OutputInterface $output
     * @param array           $connections
     * @param string          $backupPath
     */
    protected function printConnectionOverviewTable (OutputInterface $output, array $connections, $backupPath)
    {
        $rows = [];

        /**
         * @var string             $identifier
         * @var DatabaseConnection $connection
         */
        foreach ($connections as $identifier => $connection)
        {
            // Check if the connection could not be resolved.
            if (is_null($connection))
            {
                $rows[] = ['<error>- unresolved -</error>', $identifier, '-'];

                continue;
            }

            // Preserve the designated file names for the actual backup process as an actual dump
            // may take very long the actual file names would differ from the one we printed to the user
            $connection->setBackupPath($this->getBackupFilePath($backupPath, $connection->getIdentifier(), $connection->getDatabase()));

            $rows[] = [$connection->getDatabase(), $identifier, $connection->getBackupPath()];
        }

        // Print a nice table with the database name and the actual target file path
        $this->getHelper('table')
             ->setHeaders(['Database', 'Connection', 'Backup file'])
             ->setRows($rows)
             ->render($output);
    }
}
