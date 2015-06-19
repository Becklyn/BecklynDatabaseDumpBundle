<?php


namespace Becklyn\DatabaseDumpBundle\Entity;


use Doctrine\DBAL\Connection;

class DatabaseConnection
{
    /**
     * @var string Used as fallback if the database type couldn't be identified
     */
    const TYPE_UNKNOWN = 'unknown';

    /**
     * @var string Type identifier for MySQL databases
     */
    const TYPE_MYSQL = 'mysql';

    /**
     * @var string Type identifier for PostgreSQL databases
     */
    const TYPE_POSTGRESQL = 'pgsql';

    /**
     * @var string Type identifier for SQLite databases
     */
    const TYPE_SQLITE = 'sqlite';


    /**
     * @var string
     */
    private $identifier;


    /**
     * @var string
     */
    private $host;


    /**
     * @var int
     */
    private $port;


    /**
     * @var string
     */
    private $username;


    /**
     * @var string
     */
    private $password;


    /**
     * @var string
     */
    private $database;


    /**
     * @var string
     */
    private $type;


    /**
     * @var string
     */
    private $backupPath;


    /**
     * DatabaseConnection constructor.
     *
     * @param string     $identifier
     * @param Connection $connection
     */
    public function __construct ($identifier, Connection $connection = null)
    {
        $this->identifier = $identifier;
        $this->type       = $this->getDatabaseConnectionType($connection);

        if (!is_null($connection))
        {
            $this->host     = $connection->getHost();
            $this->port     = $connection->getPort();
            $this->username = $connection->getUsername();
            $this->password = $connection->getPassword();
            $this->database = $connection->getDatabase();
        }
    }


    /**
     * Determines the type of the given Symfony2 Connection and maps it to DatabaseConnection types
     *
     * @param Connection $connection
     *
     * @return string|null
     */
    protected function getDatabaseConnectionType (Connection $connection)
    {
        if (is_null($connection))
        {
            return DatabaseConnection::TYPE_UNKNOWN;
        }

        $connectionParams = $connection->getParams();

        switch ($connectionParams['driver'])
        {
            case 'pdo_mysql':
                return DatabaseConnection::TYPE_MYSQL;

            case 'pdo_pgsql':
                return DatabaseConnection::TYPE_POSTGRESQL;

            case 'sqlite':
                return DatabaseConnection::TYPE_SQLITE;

            default:
                return DatabaseConnection::TYPE_UNKNOWN;
        }
    }


    /**
     * @return string
     */
    public function getIdentifier ()
    {
        return $this->identifier;
    }


    /**
     * @param string $identifier
     */
    public function setIdentifier ($identifier)
    {
        $this->identifier = $identifier;
    }


    /**
     * @return string
     */
    public function getHost ()
    {
        return $this->host;
    }


    /**
     * @param string $host
     */
    public function setHost ($host)
    {
        $this->host = $host;
    }


    /**
     * @return int
     */
    public function getPort ()
    {
        return $this->port;
    }


    /**
     * @param int $port
     */
    public function setPort ($port)
    {
        $this->port = $port;
    }


    /**
     * @return string
     */
    public function getUsername ()
    {
        return $this->username;
    }


    /**
     * @param string $username
     */
    public function setUsername ($username)
    {
        $this->username = $username;
    }


    /**
     * @return string
     */
    public function getPassword ()
    {
        return $this->password;
    }


    /**
     * @param string $password
     */
    public function setPassword ($password)
    {
        $this->password = $password;
    }


    /**
     * @return string
     */
    public function getDatabase ()
    {
        return $this->database;
    }


    /**
     * @param string $database
     */
    public function setDatabase ($database)
    {
        $this->database = $database;
    }


    /**
     * @return string
     */
    public function getType ()
    {
        return $this->type;
    }


    /**
     * @param string $type
     */
    public function setType ($type)
    {
        $this->type = $type;
    }


    /**
     * @return string
     */
    public function getBackupPath ()
    {
        return $this->backupPath;
    }


    /**
     * @param string $backupPath
     */
    public function setBackupPath ($backupPath)
    {
        $this->backupPath = $backupPath;
    }
}
