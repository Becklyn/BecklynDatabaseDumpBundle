<?php


namespace Becklyn\MysqlDumpBundle\Entity;


use Doctrine\DBAL\Connection;

class DatabaseConnection
{
    /**
     * @var int Type identifier for MySQL databases
     */
    const TYPE_MYSQL = 0;


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
     * @var int
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

        // So far we're only dealing with MySQL connections. In the future we need
        // a proper way of detecting how to identify the connection's database type (aka the driver)
        $this->type = self::TYPE_MYSQL;

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
     * @return int
     */
    public function getType ()
    {
        return $this->type;
    }


    /**
     * @param int $type
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
