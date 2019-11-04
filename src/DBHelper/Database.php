<?php
/**
 * File containing the {@link \AppDB\DBHelper_Database} class.
 *
 * @package DBHelper
 * @see \AppDB\DBHelper_Database
 */

declare(strict_types=1);

namespace AppDB;

use PDO;
use PDOException;

/**
 * Database information container class, used to store
 * individual database connection data.
 *
 * @package DBHelper
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */
class DBHelper_Database
{
    const DEFAULT_PORT = 3306;
    
   /**
    * @var string
    */
    protected $id;
    
   /**
    * @var string
    */
    protected $host = 'localhost';
    
   /**
    * @var integer
    */
    protected $port = self::DEFAULT_PORT;
    
   /**
    * @var string
    */
    protected $name = '';
    
   /**
    * @var string
    */
    protected $username = 'root';
    
   /**
    * @var string
    */
    protected $password = '';
    
   /**
    * @var PDO
    */
    protected $pdo;
    
    protected $pdoOptions = array(
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    );
    
    public function __construct(string $id, string $name)
    {
        $this->name = $name;
        $this->id = $id;
    }
    
    public function getID() : string
    {
        return $this->id;
    }
    
    public function setHost(string $host) : DBHelper_Database
    {
        $this->host = $host;
        return $this;
    }
    
    public function setCredentials(string $username, string $password='') : DBHelper_Database
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }
    
    public function setPort(int $port) : DBHelper_Database
    {
        $this->port = $port;
        return $this;
    }
    
    public function getHost() : string
    {
        return $this->host;
    }
    
    public function getPort() : int
    {
        return $this->port;
    }
    
    public function getName() : string
    {
        return $this->name;
    }
    
    public function getUsername() : string
    {
        return $this->username;
    }
    
    public function getPassword() : string
    {
        return $this->password;
    }
    
    protected function getConnectString() : string
    {
        return sprintf(
            'mysql:host=%s;port=%s;dbname=%s',
            $this->getHost(),
            $this->getPort(),
            $this->getName()
        );
    }
    
    public function connect() : PDO
    {
        if(isset($this->pdo)) {
            return $this->pdo;
        }
        
        try 
        {
            $this->pdo = new PDO(
                $this->getConnectString(),
                $this->getUsername(),
                $this->getPassword(),
                $this->pdoOptions
            );
        } 
        catch(PDOException $e) 
        {
            if(stristr($e->getMessage(), 'driver')) 
            {
                throw new DBHelper_Exception(
                    'Cannot connect to the database: The PDO MYSQL driver is missing.',
                    null,
                    DBHelper::ERROR_CONNECTING_NO_DRIVER
                );
            }
            
            throw new DBHelper_Exception(
                sprintf('Could not connect to the database [%s].', $this->getName()),
                sprintf(
                  'Tried connecting with user [%s] on host [%s] and port [%s]. PDO native message: [%s]',
                    $this->getUsername(),
                    $this->getHost(),
                    $this->getPort(),
                    $e->getMessage()
                ),
                DBHelper::ERROR_CONNECTING
            );
        }
        
        return $this->pdo;
    }
    
    public function disconnect() : void
    {
        unset($this->pdo);
    }
    
    public function isConnected() : bool
    {
        return isset($this->pdo);
    }

    public function setInitCommand(string $command) : DBHelper_Database
    {
        $this->pdoOptions[PDO::MYSQL_ATTR_INIT_COMMAND] = $command;
        return $this;
    }
    
    public function getInitCommand() : string
    {
        $option = $this->getPDOOption(PDO::MYSQL_ATTR_INIT_COMMAND);
        if($option !== null) {
            return $option;
        }
        
        return '';
    }
    
    protected function getPDOOption($name)
    {
        if(isset($this->pdoOptions[$name])) {
            return $this->pdoOptions[$name];
        }
        
        return null;
    }
}
