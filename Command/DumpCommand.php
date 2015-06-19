<?php

namespace Becklyn\DatabaseDumpBundle\Command;

use Becklyn\DatabaseDumpBundle\Entity\DatabaseConnection;
use Becklyn\DatabaseDumpBundle\Exception\DatabaseDumpException;
use Becklyn\DatabaseDumpBundle\Exception\ProfileNameNotFoundException;
use Becklyn\DatabaseDumpBundle\Service\DatabaseDumpService;
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
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'Dumps the provided database connections. Multiple connection identifiers are separated by <info>comma (,)</info>',
                []
            )
            ->addOption(
                'path',
                'p',
                InputOption::VALUE_REQUIRED,
                "The folder path where the .sql file will be saved. Defaults to '%kernel.root_dir%/var/db_backups/'.",
                null
            )
            ->addOption(
                'profile',
                'pr',
                InputOption::VALUE_REQUIRED,
                "Determines which pre-configured database connection profile should be used. Can be used in conjunction with the <info>--connections</info> parameter.",
                null
            )
        ;
    }


    /**
     * {@inheritdoc}
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute (InputInterface $input, OutputInterface $output)
    {
        /** @var FormatterHelper $formatter */
        $formatter    = $this->getHelper('formatter');
        $dumper       = $this->getContainer()->get('becklyn.db_dump.dump');
        $dumperConfig = $this->getContainer()->get('becklyn.db_dump.configuration');

        // Print headline
        $output->writeln($formatter->formatBlock(['', '  Becklyn MySQL Database Dumper', ''], 'comment'));

        try
        {
            // Retrieve all CLI connections
            $cliArguments = $this->parseCliConnectionArguments($input);
        }
        catch (ProfileNameNotFoundException $e)
        {
            $error = $formatter->formatBlock(['', "  {$e->getMessage()}  ", ''], 'error');
            $output->writeln("\n{$error}\n");

            return 1;
        }

        $cliConnections = $cliArguments['connections'];
        $cliBackupPath = $cliArguments['directory'];

        // Retrieve the actual connections
        $connections = $dumperConfig->getDatabases($cliConnections);
        $backupPath  = $dumperConfig->getBackupDirectory($cliBackupPath);

        // If there are no database connection information available we can't dump anything
        if (empty($connections))
        {
            $error = $formatter->formatBlock(['', '  No connection data found.  ', ''], 'error');
            $output->writeln("\n{$error}\n");

            return 1;
        }

        // Configure the DatabaseConnection's backup path
        $this->configureBackupPaths($dumper, $connections, $backupPath);

        // Print the connection overview table
        $this->printConnectionOverviewTable($output, $connections);

        // Check if any connections could not be resolved and then print an error to abort.
        if (in_array(null, $connections, true))
        {
            $error = $formatter->formatBlock(['', '  Could not resolve one or more connections.  ', ''], 'error');
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
            catch (DatabaseDumpException $e)
            {
                $output->writeln('<error>failed</error>');
                $this->printDumpException($output, $e);
            }
        }

        $output->writeln("\n<info>»» Backup completed.</info>");

        return 0;
    }


    /**
     * Parses all CLI arguments for database connection identifiers
     *
     * @param InputInterface $input
     *
     * @return array|null
     *
     * @throws ProfileNameNotFoundException
     */
    protected function parseCliConnectionArguments (InputInterface $input)
    {
        $dumperConfig = $this->getContainer()->get('becklyn.db_dump.configuration');

        // Gather all connections that have been provided either via --profile or --connections
        $profileName = $input->getOption('profile');
        $profile     = $dumperConfig->getProfileConfiguration($profileName);

        // Throw an error if the user has requested to use a non-existing profile
        if ($profileName && is_null($profile))
        {
            throw new ProfileNameNotFoundException("Could not resolve profile '{$profileName}'.");
        }

        $profileConnections   = isset($profile['connections']) ? $profile['connections'] : [];
        $sanitizedConnections = $this->flattenCommaSeparatedInput($input->getOption('connections'), true);

        // Combine all connection identifiers and remove possible duplicates
        $cliConnections = array_unique(array_merge($sanitizedConnections, $profileConnections));


        // Retrieve the profile's backup path
        $backupPath = $profile['directory'];

        // If none is set we fall back to the CLI argument,
        // which is either the one the user has passed or the default value
        if (is_null($backupPath))
        {
            $backupPath = $input->getOption('path');
        }

        return [
            'connections' => $cliConnections,
            'directory'   => $backupPath,
            'profileName' => $profileName,
        ];
    }


    /**
     * Takes an input of array or string and searches for comma separated values and flattens them.
     * Optionally remove duplicate entries.
     *
     * @param string|array $input
     * @param bool         $unique
     *
     * @return array<string>
     */
    private function flattenCommaSeparatedInput ($input, $unique = false)
    {
        // If the input is a string then separate right array
        if (is_string($input))
        {
            $values = explode(',', $input);
            if ($unique)
            {
                $values = array_unique($values);
            }

            return $values;
        }

        // If the input is an array then try to flatten each element
        if (is_array($input) && count($input) == 1)
        {
            $flattenArray = [];

            foreach ($input as $element)
            {
                $flattenArray = array_merge($flattenArray, explode(',', $element));
            }

            if ($unique)
            {
                $flattenArray = array_unique($flattenArray);
            }

            return $flattenArray;
        }

        // Do nothing
        return $input;
    }


    /**
     * Sets the backup path for the given DatabaseConnections
     *
     * @param DatabaseDumpService $dumper
     * @param array               $connections
     * @param string              $backupPath
     */
    protected function configureBackupPaths (DatabaseDumpService $dumper, array $connections, $backupPath)
    {
        /**
         * @var string             $identifier
         * @var DatabaseConnection $connection
         */
        foreach ($connections as $identifier => $connection)
        {
            // Filter out connections that could not be resolved
            if (is_null($connection))
            {
                continue;
            }

            $backupFilePath = $this->buildBackupPath($backupPath, $connection->getIdentifier(), $connection->getDatabase());

            // Preserve the designated file names for the actual backup process as an actual dump
            // may take very long the actual file names would differ from the one we printed to the user
            $dumper->configureBackupPath($connection, $backupFilePath);
        }
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
    protected function buildBackupPath ($backupPath, $identifier, $database)
    {
        $rootDir = dirname($this->getContainer()->get('kernel')->getRootDir());

        // When the backup path is inside the root dir we convert the path to a relative path to shorten the output
        if (strpos($backupPath, $rootDir) === 0)
        {
            $backupPath = str_replace($rootDir, '.', $backupPath);
        }

        return sprintf('%s/%s_backup_%s__%s.sql', rtrim($backupPath, '/'), date('Y-m-d_H-i'), $identifier, $database);
    }


    /**
     * Prints an error box to the UI for the given exception
     *
     * @param OutputInterface       $output
     * @param DatabaseDumpException $e
     */
    protected function printDumpException (OutputInterface $output, DatabaseDumpException $e)
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
     */
    protected function printConnectionOverviewTable (OutputInterface $output, array $connections)
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
                $rows[] = ['<error>- unresolved -</error>', $identifier, '-', '-'];

                continue;
            }

            $rows[] = [$connection->getDatabase(), $identifier, $connection->getType(), $connection->getBackupPath()];
        }

        // Print a nice table with the database name and the actual target file path
        $this->getHelper('table')
             ->setHeaders(['Database', 'Connection', 'Type', 'Backup file'])
             ->setRows($rows)
             ->render($output);
    }
}
