<?php
/**
 * File containing the {@link DBHelper} class.
 * @package Helpers
 * @subpackage DBHelper
 * @see DBHelper
 */

declare(strict_types=1);

namespace AppDB;

/**
 * Simple database utility class used tu run queries against
 * the database using the main PDO object. Does not abstract
 * database access by design; merely simplifies the code
 * required to run a query.
 *
 * Queries themselves have to be created manually, making it
 * easier to maintain individual queries as opposed to a know-
 * it-all approach that is often quite hermetic.
 *
 * @package Helpers
 * @subpackage DBHelper
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */
class DBHelper
{
    const ERROR_EXECUTING_QUERY = 33871001;
    
    const ERROR_PREPARING_QUERY = 33871002;
    
    const ERROR_INSERTING = 33871003;
    
    const ERROR_FETCHING = 33871004;
    
    const ERROR_CONNECTING = 33871005;
    
    const ERROR_SELECTING_DATABASE = 33871006;
    
    const ERROR_INVALID_VARIABLE_TYPE = 33871007;
    
    const ERROR_CANNOT_ROLL_BACK_TRANSACTION = 33871008;
    
    const ERROR_CANNOT_COMMIT_TRANSACTION = 33871009;
    
    const ERROR_CANNOT_START_TRANSACTION = 33871010;
    
    const ERROR_NO_ACTIVE_STATEMENT = 33871011;
    
    const ERROR_TRANSACTION_REQUIRED_FOR_OPERATION = 33871012;
    
    const ERROR_CONNECTING_NO_DRIVER = 33871013;
    
    const ERROR_NOT_A_DBHELPER_COLLECTION = 33871014;
    
    const ERROR_NO_PARENT_RECORD_SPECIFIED = 33871015;
    
    const ERROR_INVALID_PARENT_RECORD = 33871016;
    
    const ERROR_DATABASE_ALREADY_EXISTS = 33871017;
    
    const ERROR_CANNOT_SELECT_UNKNOWN_DATABASE = 33871018;
    
    const ERROR_CANNOT_SET_UNKNOWN_OPTION = 33871019;
    
    const ERROR_EVENT_NOT_CALLABLE = 33871020;
    
    const ERROR_NO_DATABASES_ADDED = 33871021;
    
    const ERROR_NO_DATABASE_SELECTED = 33871022;
    
    /**
     * Counter for the amount of queries run during a request.
     * @var int
     */
    protected static $queryCount = 0;

    /**
     * The statement object for the last query that was run, if any.
     * @var \PDOStatement
     */
    protected static $activeStatement = null;
    
   /**
    * @var DBHelper_Database[]
    */
    protected static $databases = array();

    
    protected static $startTime;
    
   /**
    * @var array
    * @see DBHelper::getSQL()
    */
    protected static $activeQuery;
    
   /**
    * @var array
    */
    protected static $options = null;
    
    /**
     * @var string
     */
    protected static $selectedDB = null;
    
    /**
     * @var \PDO
     */
    protected static $activeDB = null;
    
    /**
     * @var bool
     */
    protected static $initDone = false;
    
   /**
    * Caches column names that were checked to exist.
    * @var array
    * @see DBHelper::columnExists()
    */
    protected static $cachedColumnExist = array();
    
    /**
     * Executes a query string with the specified variables. Uses
     * the PDO->prepare() method, and returns the result of the
     * PDOStatement->execute() method. If the query fails, the
     * error information can be accessed via {@link getErrorMessage()}.
     *
     * @param int $operationType The type of operation to execute.
     * @param string $sqlStatement The full SQL query to run with placeholders for variables
     * @param array $variables Associative array with placeholders and values to replace in the query
     * @param bool $exceptionOnError Whether to trigger an exception if an error occurrs.
     * @return boolean
     * 
     * @see getErrorMessage()
     * @see getErrorCode()
     * 
     * @see DBHelper::ERROR_PREPARING_QUERY
     * @see DBHelper::ERROR_EXECUTING_QUERY
     */
    public static function execute(int $operationType, string $sqlStatement, array $variables = array(), bool $exceptionOnError=true) : bool
    {
        self::init();
        
        if(self::isQueryTrackingEnabled()) {
            self::$startTime = microtime(true);
        }

        self::$activeQuery = array($sqlStatement, $variables);
        
        if(DBHelper_OperationTypes::isWriteOperation($operationType) && self::hasListener('BeforeDBWriteOperation')) {
            $event = self::triggerEvent('BeforeDBWriteOperation', array($operationType, $sqlStatement, $variables));
            if($event->isCancelled()) {
                return true;
            }
        }
        
        try
        {
            $stmt = self::$activeDB->prepare($sqlStatement);
            
            if(!$stmt) 
            {
                if(!$exceptionOnError) {
                    return false;
                }
                
                self::throwException(
                    self::ERROR_PREPARING_QUERY,
                    'Could not prepare query'
                );
            }
        } 
        catch(\PDOException $e)
        {
            if(!$exceptionOnError) {
                return false;
            }
            
            self::throwException(
                self::ERROR_PREPARING_QUERY, 
                'Could not prepare query', 
                null, 
                $e
            );
        }
        
        self::$activeStatement = $stmt;
        
        foreach($variables as $name => $value) {
            if($value instanceof \DateTime) {
                $variables[$name] = $value->format('Y-m-d H:i:s');
            }
        }
        
        try
        {
            $result = self::$activeStatement->execute($variables);
            
            if(!$result)
            {
                if(!$exceptionOnError) {
                    return false;
                }
                
                self::throwException(
                    self::ERROR_EXECUTING_QUERY,
                    'Query execution failed'
                );
            }

            self::$queryCount++;
        } 
        catch(\PDOException $e)
        {
            if(!$exceptionOnError) {
                return false;
            }
            
            self::throwException(
                self::ERROR_EXECUTING_QUERY, 
                'Query execution failed',
                null, 
                $e
            );
        }

        return $result;
    }
    
   /**
    * Runs an insert query and returns the insert ID if applicable.
    * Note that this method requires a full INSERT query, it does
    * not automate anything. The only difference to the {@link execute()}
    * method is that it returns the insert ID.
    *
    * For tables that have no autoincrement fields, this will return
    * a null value. As it triggers an exception in all cases something
    * could go wrong, there is no need to check the return value of
    * this method.
    *
    * @param string $sqlStatement
    * @param array $variables
    * @throws DBHelper_Exception
    * @return string The inserted ID.
    * 
    * @see DBHelper::ERROR_INSERTING
    */
    public static function insert(string $sqlStatement, array $variables = array()) : string
    {
        if(self::executeAndRegister(DBHelper_OperationTypes::TYPE_INSERT, $sqlStatement, $variables, false)) {
            return self::getDB()->lastInsertId();
        }
        
        self::throwException(
            self::ERROR_INSERTING,
            'Failed inserting a record'
        );
    }

    /**
     * Registers a query by adding it to the interal queries cache.
     * They can be retrieved using the {@link getQueries()} method,
     * and the last query that was run can be retrieved using the
     * {@link getSQL()} and {@link getSQLHighlighted()} methods.
     *
     * @param string $statement
     * @param array $variables
     */
    protected static function registerQuery($operationType, $statement, $variables=array(), $result)
    {
        if (self::isQueryLoggingEnabled()) {
            self::log(self::getSQL());
        }
        
        if(self::isDebuggingEnabled()) {
            self::debugQuery($result);
        }

        if(self::isQueryTrackingEnabled()) {
            $time = microtime(true)-self::$startTime;
            self::$queries[] = array($statement, $variables, $time, $operationType);
        } 
    }
    
    /**
     * Returns the error message from the last query that was run, if any.
     * @return string|null
     * @see getErrorCode()
     */
    public static function getErrorMessage()
    {
        if (!isset(self::$activeStatement)) {
            return null;
        }

        $errorInfo = self::$activeStatement->errorInfo();
        if (isset($errorInfo[2])) {
            return $errorInfo[2];
        }

        $error = error_get_last();
        if ($error) {
            return $error['message'] . ' in ' . $error['file'] . ':' . $error['line'];
        }

        return null;
    }

    /**
     * Returns the error code from the last query that was run, if any.
     * @return string|null
     * @see getErrorMessage()
     */
    public static function getErrorCode()
    {
        if (!isset(self::$activeStatement)) {
            return null;
        }

        $errorInfo = self::$activeStatement->errorInfo();
        if (isset($errorInfo[0]) && $errorInfo[0] != '00000') {
            return $errorInfo[0];
        }

        $error = error_get_last();
        if ($error) {
            return $error['type'];
        }

        return null;
    }

    /**
     * Retrieves the raw SQL query string from the last query, if any.
     * @return string|NULL
     */
    public static function getSQL()
    {
        if (!isset(self::$activeQuery)) {
            return '';
        }

        return self::formatQuery(self::$activeQuery[0], self::$activeQuery[1]);
    }
    
    public static function formatQuery($query, $variables)
    {
        if(empty($variables)) {
            return $query;
        }
        
        $replaces = array();
        foreach ($variables as $placeholder => $value) {
            $placeholder = ':'.ltrim($placeholder, ':');
            $replaces[$placeholder] = "'".$value."'";
        }
        
        return str_replace(array_keys($replaces), array_values($replaces), $query);
    }

    public static function getSQLHighlighted()
    {
        $sql = self::getSQL();
        if(!empty($sql)) {
            return \AppUtils\ConvertHelper::highlight_sql(\AppUtils\ConvertHelper::normalizeTabs($sql, true));
        }
        
        return null;
    }

   /**
    * Runs an update query. This is an alias for the {@link execute()}
    * method, which exists for semantic purposes and the possibility
    * to add specific functionality at a later time. It is recommended
    * to use this method if you run UPDATE queries.
    *
    * @param string $statement The full SQL query to run with placeholders for variables
    * @param array $variables Associative array with placeholders and values to replace in the query
    * @return boolean
    */
    public static function update(string $statement, array $variables = array()) : bool
    {
        return self::executeAndRegister(DBHelper_OperationTypes::TYPE_UPDATE, $statement, $variables);
    }
    
   /**
    * Executes the query and registers it internally.
    * 
    * @param int $operationType The type of operation to execute.
    * @param string $sqlStatement
    * @param array $variables
    * @param bool $exceptionOnError Whether to trigger an exception if an error occurrs.
    * @return boolean
    */
    protected static function executeAndRegister(int $operationType, string $sqlStatement, array $variables=array(), bool $exceptionOnError=true) : bool
    {
        $result = self::execute($operationType, $sqlStatement, $variables, $exceptionOnError);
        self::registerQuery($operationType, $sqlStatement, $variables, $result);
        return $result;
    }

   /**
    * Runs a delete query. This is an alias for the {@link execute()}
    * method, which exists for semantic purposes and the possibility
    * to add specific functionality at a later time. It is recommended
    * to use this method if you run DELETE queries.
    *
    * @param string $statement The full SQL query to run with placeholders for variables
    * @param array $variables Associative array with placeholders and values to replace in the query
    * @return boolean
    */
    public static function delete(string $statement, array $variables = array()) : bool
    {
        return self::executeAndRegister(DBHelper_OperationTypes::TYPE_DELETE, $statement, $variables);
    }

    /**
     * Fetches a single entry as an associative array from a SELECT query.
     * @param string $statement The full SQL query to run with placeholders for variables
     * @param array $variables Associative array with placeholders and values to replace in the query
     * @throws DBHelper_Exception
     * @return null|array
     */
    public static function fetch(string $statement, array $variables = array()) :?array
    {
        self::executeAndRegister(DBHelper_OperationTypes::TYPE_SELECT, $statement, $variables);
        
        $fetch = self::$activeStatement->fetch(\PDO::FETCH_ASSOC);
        
        if($fetch === false) {
            $fetch = null;
        }
        
        return $fetch;
    }
   
   /**
    * Like {@link fetch()}, but builds the query dynamically to
    * fetch data from a single table.
    * 
    * @param string $table The table name
    * @param array $where Any where column values required
    * @param array $columns The columns to fetch. Defaults to all columns if empty.
    * @return NULL|array
    */
    public static function fetchData($table, $where=array(), $columns=array()) : ?array
    {
        $select = '*';
        if(!empty($columns)) 
        {
            $entries = array();
            foreach($columns as $name) {
                $entries[] = '`'.$name.'`';
            } 
            
            $select = implode(', ', $entries);
        }
        
        $whereString = self::buildWhereFieldsStatement($where);
        
        $query = sprintf(
            "SELECT
                %s
            FROM
                `%s`
            WHERE
                %s",
            $select,
            $table,
            $whereString
        );
        
        return DBHelper::fetch($query, $where);
    }

    /**
     * Fetches all entries matching a SELECT query, as an indexed array
     * with associative arrays for each record.
     *
     * @param string $statement The full SQL query to run with placeholders for variables
     * @param array $variables Associative array with placeholders and values to replace in the query
     * @throws DBHelper_Exception
     * @return array
     */
    public static function fetchAll($statement, $variables = array())
    {
        self::executeAndRegister(DBHelper_OperationTypes::TYPE_SELECT, $statement, $variables);
        
        $result = self::$activeStatement->fetchAll(\PDO::FETCH_ASSOC);
        if($result===false) {
            self::throwException(
                self::ERROR_FETCHING,
                'Failed fetching a record'
            );
        }
        
        return $result;
    }

    protected static function throwException($code, $title=null, $errorMessage=null, \PDOException $e=null)
    {
        if(empty($title)) {
            $title = 'Query failed';
        }
        
        if(empty($errorMessage)) {
            $errorMessage = self::getErrorMessage();
        }
        
        if(empty($errorMessage)) {
            $errorMessage = '<i>No message specified</i>';
        }

        if($e) {
            $errorMessage .= 'Native message: '.$e->getMessage();
        }
        
        $info = '';
        $placeholderInfo = '';
        
        if(!empty(self::$activeQuery)) 
        {
            // retrieve a list of all placeholders used in the query
            $params = array();
            preg_match_all('/[:]([a-zA-Z0-9_]+)/', self::$activeQuery[0], $params, PREG_PATTERN_ORDER);
            
            $paramNames = array();
            if(isset($params[1]) && isset($params[1][0])) {
                $paramNames = array_unique($params[1]);
            }
            
            $tokens = array();
            $errors = false;
            foreach($paramNames as $name) {
                $tokenInfo = '';
                
                $foundName = null;
                if(array_key_exists($name, self::$activeQuery[1])) {
                    $foundName = $name;
                }
                
                if(array_key_exists(':'.$name, self::$activeQuery[1])) {
                    $foundName = ':'.$name;    
                } 
                
                if($foundName) {
                    $tokenInfo = json_encode(self::$activeQuery[1][$foundName]);
                } else {
                    $errors = true;
                    $tokenInfo = '<i class="text-error">Placeholder not specified in values list</i>';
                }
                
                $tokens[] = $name .= ' = ' . $tokenInfo;
            }
            
            if(isset(self::$activeQuery[1])) 
            {
                $names = array_keys(self::$activeQuery[1]);
                
                foreach($names as $name) 
                {
                    if(!in_array(ltrim($name, ':'), $paramNames)) {
                        $errors = true;
                        $tokens[] = $name .= ' = <i class="text-error">No matching placeholder in query</i>';
                    }
                }
            }
                
            if($errors) {
                $placeholderInfo = 'Analysis: Placeholders have inconsistencies, see detail below.<br/>';
            }
        }
        
        $database = self::getSelectedDatabase();
        
        $message = sprintf(
            'DB error message: [%s]<br/>' .
            'Database: %s@%s on %s<br/>',
            $errorMessage,
            $database->getUsername(),
            $database->getName(),
            $database->getHost()
        );
            
        $sql = self::getSQLHighlighted();
        if(!empty($sql)) {
            $info .=
            '<br>SQL (with simulated variable values):<br>' . 
            $sql .
            $placeholderInfo.
            'Placeholders: '.count($paramNames);

            if(!empty($tokens)) {
                $message .= $info.'<br/><ul class="unstyled"><li>'.implode('</li><li>', $tokens).'</li></ul>';
            }
        }

        throw new DBHelper_Exception(
            $title,
            $message,
            $code,
            $e
        );
    }

    /**
     * Retrieves the current query count. Obviously, this has to be
     * done at the end of the request to be accurate for the total
     * number of queries in a request.
     *
     * @return number
     */
    public static function getQueryCount()
    {
        return self::$queryCount;
    }

    public static function getLimitSQL($limit = 0, $offset = 0)
    {
        if ($limit < 1 && $offset < 1) {
            return '';
        }

        $limitSQL = 'LIMIT ';
        if ($offset > 0) {
            $limitSQL .= $offset . ',';
        }
        $limitSQL .= $limit;

        return $limitSQL . PHP_EOL;
    }

    protected static $queries = array();

   /**
    * Retrieves all queries executed so far, optionally restricted
    * to only the specified types.
    * 
    * @param int[] $types
    * @return mixed[]
    */
    public static function getQueries($types=null)
    {
        if(empty($types)) {
            return self::$queries;
        }
        
        $queries = array();
        $total = count(self::$queries);
        for($i=0; $i<$total; $i++) {
            if(in_array(self::$queries[$i][3], $types)) {
                $queries[] = self::$queries[$i];
            }
        }
        
        return $queries;
    }
    
   /**
    * Retrieves information about all queries made up to this point,
    * but only write operations.
    * 
    * @return mixed[]
    */
    public static function getWriteQueries()
    {
        return self::getQueries(DBHelper_OperationTypes::getWriteTypes());
    }
    
    public static function getSelectQueries()
    {
        return self::getQueries(array(DBHelper_OperationTypes::TYPE_SELECT));
    }
    
    public static function countSelectQueries()
    {
        return self::countQueries(array(DBHelper_OperationTypes::TYPE_SELECT));
    }
    
    public static function countWriteQueries()
    {
        return self::countQueries(DBHelper_OperationTypes::getWriteTypes());
    }
    
   /**
    * Counts how many queries were executed while 
    * query tracking was enabled, up to this point.
    * Any queries executed before query tracking was
    * enabled will be excluded from this count.
    * 
    * @param array $types
    * @return int
    * @see DBHelper::getQueryCount()
    */
    public static function countQueries(array $types=array()) : int
    {
        if(empty($types)) {
            return count(self::$queries);
        }
        
        $total = count(self::$queries);
        $result = 0;
        for($i=0; $i<$total; $i++) {
            if(in_array(self::$queries[$i][3], $types)) {
                $result++;
            }
        }
        
        return $result;
    }

    /**
     * Retrieves a list of all tables present in the
     * database; Only shows the tables that the user
     * has access to. Returns an indexed array with
     * table names.
     *
     * @throws DBHelper_Exception
     * @return array
     * @deprecated
     * 
     * @see DBHelper::fetchTableNames()
     */
    public static function getTablesList() : array
    {
        return self::fetchTableNames();
    }

    /**
     * Truncates the specified table (deletes all rows if any).
     * Returns true on success, false on failure.
     *
     * @param string $tableName
     * @throws DBHelper_Exception
     * @return boolean
     */
    public static function truncate(string $tableName)
    {
        $query = 'TRUNCATE TABLE `' . $tableName . '`';
        return self::executeAndRegister(DBHelper_OperationTypes::TYPE_TRUNCATE, $query);
    }

    /**
     * Used to keep track of transactions.
     * @var boolean
     */
    protected static $transactionStarted = false;

    /**
     * Starts a new transaction. Don't forget to either commit or
     * roll back once you have run all your statements!
     *
     * @return boolean
     * @throws DBHelper_Exception
     * @see commitTransaction()
     * @see rollbackTransaction()
     */
    public static function startTransaction()
    {
        self::log('Starting a new transaction.');

        if (self::$transactionStarted) {
            throw new DBHelper_Exception(
                'Cannot start another transaction',
                'You cannot start a new transaction within a transaction; Commit or rollback the current transaction before starting a new one.',
                self::ERROR_CANNOT_START_TRANSACTION
            );
        }

        self::$transactionStarted = true;

        return self::executeAndRegister(DBHelper_OperationTypes::TYPE_TRANSACTION, 'START TRANSACTION');
    }

    /**
     * Checks whether a transaction has been started.
     * @return boolean
     */
    public static function isTransactionStarted()
    {
        return self::$transactionStarted;
    }

    /**
     * Commits a previously started transaction by applying
     * all changes made permanently.
     *
     * @return boolean
     * @throws DBHelper_Exception
     * @see rollbackTransaction()
     * @see startTransaction()
     */
    public static function commitTransaction()
    {
        self::log('Committing the transaction.');

        if (!self::$transactionStarted) {
            throw new DBHelper_Exception(
                'Cannot commit transaction',
                'No transaction was started to commit.',
                self::ERROR_CANNOT_COMMIT_TRANSACTION
            );
        }

        self::$transactionStarted = false;

        return self::executeAndRegister(DBHelper_OperationTypes::TYPE_TRANSACTION, 'COMMIT');
    }

    /**
     * Rolls back a previously started transaction by cancelling
     * all changes.
     *
     * @throws DBHelper_Exception
     * @return boolean
     * @see commitTransaction()
     * @see startTransaction()
     */
    public static function rollbackTransaction()
    {
        self::log('Rolling back the transaction.');

        if (!self::$transactionStarted) {
            throw new DBHelper_Exception(
                'Cannot roll back transaction',
                'No transaction was started to roll back.',
                self::ERROR_CANNOT_ROLL_BACK_TRANSACTION
            );
        }

        self::$transactionStarted = false;

        return self::executeAndRegister(DBHelper_OperationTypes::TYPE_TRANSACTION, 'ROLLBACK');
    }

   /**
    * Initializes the helper: connects to the
    * database, and triggers the <code>Init</code>
    * event.
    * 
    * @throws DBHelper_Exception
    * @see DBHelper::onInit()
    * 
    * @see DBHelper::ERROR_NO_DATABASES_ADDED
    */
    public static function init() : void
    {
        if(self::$initDone) {
            return;
        }
        
        if(empty(self::$databases))
        {
            throw new DBHelper_Exception(
                'Cannot initialize: no databases were added.',
                null,
                self::ERROR_NO_DATABASES_ADDED
            );
        }
        
        DBHelper_OperationTypes::init();
        
        self::selectDatabase(self::$selectedDB);
        self::$initDone = true;
        
        self::triggerEvent('Init');
    }
    
   /**
    * Checks whether the helper has been initialized yet.
    * @return bool
    * @see DBHelper::init()
    */
    public static function isInitialized() : bool
    {
        return self::$initDone;
    }
    
   /**
    * Triggers an event and returns the event handler instance.
    * 
    * @param string $eventName
    * @param array $args
    * @return DBHelper_Event|NULL
    */
    protected static function triggerEvent($eventName, $args=array())
    {
        $handler = new DBHelper_Event($eventName, $args);
        
        if(!self::hasListener($eventName)) {
            return null;
        }
        
        foreach(self::$eventHandlers[$eventName] as $listener) {
            $data = $listener['data'];

            array_unshift($data, $handler);
            
            call_user_func_array($listener['callback'], $data);
            
            if($handler->isCancelled()) {
                break;
            }
        }
        
        return $handler;
    }
    
   /**
    * Checks if one or more event listeners have been added
    * to the specified event.
    * 
    * @param string $eventName
    * @return bool
    */
    public static function hasListener(string $eventName) : bool
    {
        return isset(self::$eventHandlers[$eventName]) && !empty(self::$eventHandlers[$eventName]);
    }
    
   /**
    * Retrieves the IDs of all listeners currently present
    * for the specified event.
    * 
    * @param string $eventName
    * @return array
    */
    public static function getListenerIDs(string $eventName) : array
    {
        if(isset(self::$eventHandlers[$eventName])) {
            return array_keys(self::$eventHandlers[$eventName]); 
        }
        
        return array();
    }

    protected static $eventCounter = 0;
    
    protected static $eventHandlers = array();
    
   /**
    * Adds an even listener for the <code>Init</code> event.
    * 
    * @param callable $handler
    * @param mixed $data
    * @return int
    */
    public static function onInit($handler, $data=null) : int
    {
        return self::addListener('Init', $handler, $data);
    }
    
   /**
    * Adds an event listener, and returns its ID.
    * 
    * @param string $eventName
    * @param callable $handler
    * @param mixed $data
    * @throws DBHelper_Exception
    * @return int
    * 
    * @see DBHelper::ERROR_EVENT_NOT_CALLABLE
    */
    protected static function addListener(string $eventName, $handler, $data=null) : int
    {
        self::$eventCounter++;
        
        if(!is_callable($handler)) 
        {
            throw new DBHelper_Exception(
                sprintf('Cannot add listener to event [%s]: the callback is not callable.', $eventName),
                sprintf('Callback info: [%s].', \AppUtils\ConvertHelper::callback2string($handler)),
                self::ERROR_EVENT_NOT_CALLABLE
            );
        }
        
        if(!isset(self::$eventHandlers[$eventName])) {
            self::$eventHandlers[$eventName] = array();
        }
        
        if(empty($data)) {
            $data = array();
        }
        
        if(!is_array($data)) {
            $data = array($data);
        }
        
        $id = count(self::$eventHandlers[$eventName]) + 1;
        self::$eventHandlers[$eventName][$id] = array(
            'id' => $id,
            'callback' => $handler,
            'data' => $data
        );
        
        return $id;
    }
    
   /**
    * Removes all listeners to the specified event, if any.
    * @param string $eventName
    */
    public static function removeListeners($eventName)
    {
        if(isset(self::$eventHandlers[$eventName])) {
            unset(self::$eventHandlers[$eventName]);
        }
    }
    
   /**
    * Removes a specific event listener by its ID, if it exists.
    * @param string $eventID
    */
    public static function removeListener($eventID)
    {
        foreach(self::$eventHandlers as $name => $listeners) 
        {
            $ids = array_keys($listeners);
            
            foreach($ids as $id) 
            {
                if($id == $eventID) {
                    unset(self::$eventHandlers[$name][$id]);
                    return;
                }
            }
        }
    }
    
   /**
    * Adds a database to connect to. Automatically selects
    * that database if none has been selected before.
    * 
    * @param string $id
    * @param string $name
    * @return \AppDB\DBHelper_Database
    * 
    * @see DBHelper::ERROR_DATABASE_ALREADY_EXISTS
    */
    public static function addDatabase(string $id, string $name) : DBHelper_Database
    {
        if(isset(self::$databases[$id]))
        {
            throw new DBHelper_Exception(
                sprintf('Cannot add new database [%s], ID already exists.', $id),
                null,
                self::ERROR_DATABASE_ALREADY_EXISTS
            );
        }
        
        self::$databases[$id] = new DBHelper_Database($id, $name);
        
        if(!isset(self::$selectedDB)) {
            self::$selectedDB = $id;
        }
        
        return self::$databases[$id];
    }
    
   /**
    * Selects a database by its ID.
    * 
    * @param string $id
    * @throws DBHelper_Exception
    * 
    * @see DBHelper::ERROR_CANNOT_SELECT_UNKNOWN_DATABASE
    */
    public static function selectDatabase(string $id) : void
    {
        if(isset(self::$databases[$id])) {
            self::$selectedDB = $id;
            self::$activeDB = self::getDB();
            return;
        }
        
        throw new DBHelper_Exception(
            sprintf(
                'Cannot select database [%s], no such database has been added.',
                $id
            ),
            sprintf(
                'Available databases are [%s].',
                implode(', ', array_keys(self::$databases))
            ),
            self::ERROR_CANNOT_SELECT_UNKNOWN_DATABASE
        );
    }

    /**
     * Retrieves the PDO database connection object for
     * the currently selected DB account.
     *
     * @throws DBHelper_Exception
     * @return \PDO
     */
    public static function getDB()
    {
        $database = self::getSelectedDatabase();

        return $database->connect();
    }
    
   /**
    * Retrieves the currently selected database, provided
    * at least one database has been added, or a specific
    * database has been selected.
    * 
    * @throws DBHelper_Exception
    * @return DBHelper_Database
    * 
    * @see DBHelper::ERROR_NO_DATABASE_SELECTED
    */
    public static function getSelectedDatabase() : DBHelper_Database
    {
        if(isset(self::$databases[self::$selectedDB])) {
            return self::$databases[self::$selectedDB];
        }
        
        throw new DBHelper_Exception(
            'Cannot get selected database: no database selected.',
            null,
            self::ERROR_NO_DATABASE_SELECTED
        );
    }

    /**
     * Simple helper method for retrieving the counter from a
     * count query: the result of the query merely has to
     * contain a "count" field which is then returned. In all
     * other cases, this will return 0.
     *
     * @param string $statement
     * @param array $variables
     * @return int
     */
    public static function fetchCount($statement, $variables = array())
    {
        $entry = self::fetch($statement, $variables);
        if (is_array($entry) && isset($entry['count'])) {
            return $entry['count'];
        }

        return 0;
    }

    /**
     * Builds a limit statement to append to a query string
     * using the specified limit and offset values. Returns
     * an empty string if both are set to 0.
     *
     * @param int $limit
     * @param int $offset
     * @return string
     */
    public static function buildLimitStatement($limit = 0, $offset = 0)
    {
        return self::getLimitSQL($limit, $offset);
    }

    const INSERTORUPDATE_UPDATE = 'upd';
    
    /**
     * Utility method that either inserts or updates an existing record.
     *
     * @param string $table
     * @param array $data
     * @param array $primaryFields
     * @return string|integer The insert ID in case of an insert operation, or the update status code.
     * @see DBHelper::INSERTORUPDATE_UPDATE
     */
    public static function insertOrUpdate($table, $data, $primaryFields)
    {
        $checkField = $primaryFields[0]; // used in the query to check if a record exists

        $whereTokens = array();
        $checkVariables = array(); // variables set for the fetch query
        foreach ($primaryFields as $fieldName) {
            if (!array_key_exists($fieldName, $data)) {
                throw new DBHelper_Exception(
                    'Database configuration error',
                    sprintf(
                        'Cannot insert or update a record, the value for primary field %1$s is not included in the data set. Data set contains the following keys: %2$s.',
                        $fieldName,
                        implode(', ', array_keys($data)),
                        self::ERROR_INSERTING
                    )
                );
            }
            $whereTokens[] = "`$fieldName`=:$fieldName";
            $checkVariables[':' . $fieldName] = $data[$fieldName];
        }
        $where = implode(" AND ", $whereTokens);

        $variables = array();
        $setTokens = array(); // set statements for the update or insert statements
        foreach ($data as $fieldName => $value) {
            $variables[':' . $fieldName] = $value;
            $setTokens[] = "`$fieldName`=:$fieldName";
        }
        $set = implode(', ', $setTokens);

        $entry = self::fetch(
            "SELECT
				`$checkField`
			FROM
				`$table`
			WHERE
				$where",
            $checkVariables
        );
        
        if (is_array($entry) && isset($entry[$checkField])) {
            self::update(
                "UPDATE
					`$table`
				SET
					$set
				WHERE
					$where",
                $variables
            );
            return self::INSERTORUPDATE_UPDATE;
        } else {
            return self::insert(
                "INSERT INTO
					`$table`
				SET
					$set",
                $variables
            );
        }
    }
    
    public static function updateDynamic($table, $data, $primaryFields)
    {
        $where = array();
        foreach ($primaryFields as $fieldName) {
            if (!array_key_exists($fieldName, $data)) {
                throw new DBHelper_Exception(
                    'Database configuration error',
                    sprintf(
                        'Cannot insert or update a record, the value for primary field %1$s is not included in the data set. Data set contains the following keys: %2$s.',
                        $fieldName,
                        implode(', ', array_keys($data))
                    ),
                    self::ERROR_INSERTING
                );
            }
            $where[$fieldName] = $data[$fieldName];
        }

        return self::update(
            sprintf(
                "UPDATE
                    `%s`
                SET
                    %s
                WHERE
                    %s",
                $table,
                self::buildSetStatement($data),
                self::buildWhereFieldsStatement($where)
            ),
            $data
        );
    }
    
   /**
    * Builds a list of columns to set values for, with value placeholders
    * named after the column names.
    * 
    * @param array $data
    * @return string
    */
    public static function buildSetStatement($data)
    {
        $tokens = array();
        $columns = array_keys($data);
        foreach($columns as $name) {
            $tokens[] = sprintf(
                "`%s`=:%s", 
                $name, 
                $name
            );
        }
        
        return implode(', ', $tokens);
    }

    /**
     * Enables debugging, which will output all queries as they are run.
     */
    public static function enableDebugging(bool $enable=true)
    {
        self::setOption('debugging', $enable);
    }

    /**
     * Disables the debug mode.
     */
    public static function disableDebugging()
    {
        self::enableDebugging(false);
    }

    public static function isDebuggingEnabled() : bool
    {
        return self::getOption('debugging') === true;
    }
    
    protected static function debugQuery($result)
    {
        if (!self::isDebuggingEnabled()) {
            return;
        }
        
        $sql = trim(self::getSQL());
        $sql = str_replace(array("\t", '    '), array(''), $sql);

        $resultDisplay = '';
        if($result===true || $result===false) {
            $resultDisplay = \AppUtils\ConvertHelper::bool2string($result);
        } else if(is_array($result)) {
            $resultDisplay = count($result).' entries';
        } else if($result===null) {
            $resultDisplay = 'NULL';
        } else {
            $resultDisplay = 'Unknown';
        }
        
        echo 
        '<pre>' . 
            $sql . PHP_EOL . 
            'Result: '. $resultDisplay .
        '</pre>';
    }

   /**
    * Retrieves a list of all tables present in the
    * database; Only shows the tables that the user
    * has access to. Returns an indexed array with
    * table names.
    *
    * @return string[]
    */
    public static function fetchTableNames() : array
    {
        $entries = self::fetchAll("SHOW TABLES");

        $tables = array();
        foreach ($entries as $entry) {
            $tables[] = $entry[key($entry)];
        }

        return $tables;
    }

    public static function dropTables()
    {
        $query = self::getDropTablesQuery();
        return self::executeAndRegister(DBHelper_OperationTypes::TYPE_DROP, $query);
    }

    public static function getDropTablesQuery()
    {
        $tables = self::fetchTableNames();
        $tableQueries = array();
        foreach ($tables as $table) {
            $tableQueries[] = sprintf('DROP TABLE `%s`;', $table);
        }

        $query = sprintf(
            "SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;" . PHP_EOL .
            "SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;" . PHP_EOL .
            "SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='TRADITIONAL';" . PHP_EOL .
            "%s" . PHP_EOL .
            "SET SQL_MODE=@OLD_SQL_MODE;" . PHP_EOL .
            "SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;" . PHP_EOL .
            "SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;",
            implode(PHP_EOL, $tableQueries)
        );

        return $query;
    }

    protected static function log($message)
    {
        if(isset(self::$logCallback)) {
            call_user_func(self::$logCallback, sprintf(
                'DBHelper | %1$s',
                $message
            ));
        }
    }
    
    protected static $logCallback = null;
    
    public static function setLogCallback($callback)
    {
        if(!is_callable($callback)) {
            throw new DBHelper_Exception(
                'Invalid logging callback',
                'The specified callback is not a callable entity.',
                self::ERROR_INVALID_LOG_CALLBACK
            );
        }
        
        self::$logCallback = $callback;
    }

   /**
    * Counts affected rows after an operation. Will return
    * -1 if no query has been run.
    * 
    * @return int
    */
    public static function countAffectedRows() : int
    {
        if(isset(self::$activeStatement)) {
            return self::$activeStatement->rowCount();
        }
        
        return -1;
    }
    
   /**
    * Checks whether the specified column exists in the target table.
    * 
    * @param string $tableName
    * @param string $columnName
    * @return boolean
    */
    public static function columnExists(string $tableName, string $columnName) : bool
    {
        $key = $tableName.'.'.$columnName;
        if(isset(self::$cachedColumnExist[$key])) {
            return self::$cachedColumnExist[$key];
        }
        
        self::$cachedColumnExist[$key] = false;
        
        $database = self::getSelectedDatabase();
        
    	$info = DBHelper::fetch(
    		"SELECT
				`COLUMN_NAME`
			FROM
				`INFORMATION_SCHEMA`.`COLUMNS`
			WHERE
				`TABLE_SCHEMA` = :dbname
			AND
				`TABLE_NAME` = :tablename
			AND
				`COLUMN_NAME` = :columnname",
    		array(
    			'dbname' => $database->getName(),
    			'tablename' => $tableName,
    			'columnname' => $columnName
    		)
    	);
    	
    	if(is_array($info) && isset($info['COLUMN_NAME'])) {
    		self::$cachedColumnExist[$key] = true;
    	}
    	
    	return self::$cachedColumnExist[$key];
    }
    
   /**
    * Fetches a single row using the {@link fetch()} method, and returns
    * the specified key from the result set. Returns null if the key is
    * not found.
    * 
    * @param string $key
    * @param string $statement
    * @param array $variables
    * @return string|NULL
    */
    public static function fetchKey($key, $statement, $variables=array())
    {
        $data = self::fetch($statement, $variables);
        if(is_array($data) && isset($data[$key])) {
            return $data[$key];
        }
        
        return null;
    }
    
   /**
    * Fetches all matching rows using the {@link fetchAll()} method, and
    * returns an indexed array with all available values for the specified
    * key in the result set. Returns an empty array if the key is not found,
    * or if no records match.
    * 
    * @param string $key
    * @param string $statement
    * @param array $variables
    * @return array
    */
    public static function fetchAllKey($key, $statement, $variables=array())
    {
        $result = array();
        
        $entries = self::fetchAll($statement, $variables);
        if (!is_array($entries)) {
            return $result;
        }
        
        $total = count($entries);
        for ($i=0; $i<$total; $i++) {
           if(isset($entries[$i][$key])) {
               $result[] = $entries[$i][$key];
           } 
        }
        
        return $result;
    }
    
   /**
    * Deletes all records from the target table matching the 
    * specified column values, if any. Otherwise, all records
    * are deleted.
    * 
    * @param string $table
    * @param array $where Associative array with column > value pairs for the where statement.
    * @return boolean
    */
    public static function deleteRecords($table, $where=array())
    {
        $query = 
        "DELETE FROM
            `".$table."`";

        $vars = array();
        
        if(!empty($where)) {
            $tokens = array();
            foreach($where as $name => $value) {
                $tokens[] = sprintf(
                    "`%s` = :%s",
                    $name,
                    $name
                );
                $vars[':'.$name] = $value;
            }
            
            $query .= " WHERE ".implode(" AND ", $tokens);
        }
        
        return self::delete($query, $vars);
    }

   /**
    * Checks whether the specified key exists in the target table.
    * 
    * Example:
    * 
    * <pre>
    * // with a single key value
    * keyExists('tablename', array('primary_key' => 2));
    * 
    * // with a complex key value
    * keyExists(
    *     'tablename', 
    *     array(
    *         'first_key' => 5,
    *         'second_key' => 'text',
    *         'third_key' => 'yes'
    *     )
    * );
    * </pre>
    * 
    * @param string $table
    * @param array $key Associative array with key => value pairs to check
    * @return boolean
    */
    public static function keyExists($table, $key)
    {
        $primaryKeys = array_keys($key);
        $checkField = $primaryKeys[0]; // used in the query to check if a record exists
        
        $whereTokens = array();
        $checkVariables = array(); 
        foreach ($key as $fieldName => $fieldValue) {
            $whereTokens[] = "`$fieldName`=:$fieldName";
            $checkVariables[':' . $fieldName] = $fieldValue;
        }
        $where = implode(" AND ", $whereTokens);
        
        $entry = self::fetch(
            "SELECT
                `$checkField`
            FROM
                `$table`
            WHERE
                $where",
            $checkVariables
        );
        
        if (is_array($entry) && isset($entry[$checkField])) {
            return true;
        }        
        
        return false;
    }
    
   /**
    * Checks whether the specified table name exists.
    * Warning: Case sensitive!
    * 
    * @param string $tableName
    * @return boolean
    */
    public static function tableExists($tableName)
    {
        $names = self::fetchTableNames();
        return in_array($tableName, $names);
    }
    
   /**
    * Inserts a record by building the SQL statement dynamically from
    * the provided data set. Note that no validation happens here: you
    * must ensure that all required columns have a value.
    * 
    * Note: columns with a null value are assumed to be nullable and will
    * be set to NULL accordingly.
    * 
    * @param string $tableName
    * @param array $data
    * @return mixed The insert ID, if any
    */
    public static function insertDynamic($tableName, $data)
    {
        $setTokens = array();
        foreach($data as $column => $value) {
            // special case for null values, which need a different syntax.
            if($value===NULL) {
                $setTokens[] = "`".$column."`=NULL";
                unset($data[$column]);
            } 
            else 
            {
                $setTokens[] = "`".$column."`=:".$column;
            }
        }
        
        return DBHelper::insert(
            sprintf(
                "INSERT INTO
                    `%s`
                SET
                    %s",
                $tableName,
                implode(', ', $setTokens)
            ),
            $data
        );
    }
    
   /**
    * Checks whether the specified table column is an auto 
    * increment column.
    * 
    * @param string $tableName
    * @param string $columnName
    * @return boolean
    */
    public static function isAutoincrementColumn($tableName, $columnName)
    {
        $data = DBHelper::fetch(
            "SELECT 
               `EXTRA`
            FROM 
                INFORMATION_SCHEMA.COLUMNS
            WHERE 
                `TABLE_NAME`=:table
            AND 
                `EXTRA` like '%auto_increment%'",
            array(
                'table' => $tableName
            )
        );
        
        if(is_array($data) && isset($data['EXTRA'])) {
            return true;
        }
        
        return false;
    }
    
   /**
    * Utility method that can be used to enforce an active
    * DB transaction. Throws an exception if a DB transaction
    * is not present.
    * 
    * @param string $operationLabel A short description for the operation that requires the transaction, added to the exception details 
    * @throws DBHelper_Exception
    */
    public static function requireTransaction($operationLabel)
    {
        if(self::isTransactionStarted()) {
           return; 
        }
        
        throw new DBHelper_Exception(
            'A transaction is required',
            sprintf(
                'A transaction is required for the following operation: %s.',
                $operationLabel
            ),
            self::ERROR_TRANSACTION_REQUIRED_FOR_OPERATION
        );
    }
    
   /**
    * Checks whether a record matching the specified 
    * fields exists in the table.
    *  
    * @param string $table
    * @param array $where
    * @return boolean
    */
    public static function recordExists($table, $where)
    {
        $query = sprintf( 
            "SELECT
                COUNT(*) AS `count`
            FROM
                `%s`
            WHERE
                %s",
            $table,
            self::buildWhereFieldsStatement($where)
        );
        
        if(self::fetchCount($query, $where) > 0) {
            return true;
        }
        
        return false;
    }
    
   /**
    * Builds the fields conditions statement from a list of
    * fields and values, connected by <code>AND</code>. The
    * bound variable names match the field names.
    *  
    * If no parameters are specified in the array, returns
    * a string <code>1</code> (one) so it can always be used
    * in a WHERE statement.
    *  
    * @param array $params
    * @return string
    */
    public static function buildWhereFieldsStatement(array $params)
    {
        if(empty($params)) {
            return '1';
        }
        
        $tokens = array();
        $keys = array_keys($params);
        
        foreach($keys as $key) 
        {
            $tokens[] = sprintf(
                "`%s`=:%s",
                $key,
                $key
            );
        }
        
        return implode(' AND ', $tokens);
    }
    
   /**
    * Adds an event listener that is called before a database write operation.
    * @param callable $eventCallback
    * @return number
    */
    public static function onBeforeWriteOperation($eventCallback, $data=null)
    {
        return self::addListener('BeforeDBWriteOperation', $eventCallback, $data);
    }
    
   /**
    * Reinitializes the helper completely, including
    * the initial configuration. Mainly used in the 
    * testsuite.
    */
    public static function reset() : void
    {
        self::$initDone = false;
        self::$activeDB = null;
        self::$databases = array();
        self::$selectedDB = null;
        self::$cachedColumnExist = array();
        self::$eventCounter = 0;
        self::$eventHandlers = array();
        self::$queries = array();
        self::$logCallback = null;
        self::$queryCount = 0;
        self::$activeStatement = null;
        self::$activeQuery = null;
        self::$startTime = null;
        self::$options = null;
    }
    
    protected static function getDefaultOptions()
    {
        return array(
            'track-queries' => false,
            'log-queries' => false,
            'debugging' => false
        );
    }
    
    public static function enableQueryLogging(bool $enable=true) : void
    {
        self::setOption('log-queries', $enable);
    }

    public static function disableQueryLogging() : void
    {
        self::enableQueryLogging(false);
    }
    
    public static function isQueryLoggingEnabled() : bool
    {
        return self::getOption('log-queries') === true;
    }
    
    public static function enableQueryTracking(bool $enable=true) : void
    {
        self::setOption('track-queries', $enable);
    }
    
    public static function disableQueryTracking() : void
    {
        self::enableQueryTracking(false);
    }
    
    public static function isQueryTrackingEnabled() : bool
    {
        return self::getOption('track-queries') === true;
    }
    
   /**
    * Sets an internal option.
    * 
    * @param string $name
    * @param mixed $value
    * @throws DBHelper_Exception
    * 
    * @see DBHelper::ERROR_CANNOT_SET_UNKNOWN_OPTION
    */
    protected static function setOption(string $name, $value) : void
    {
        self::initOptions();
        
        if(isset(self::$options[$name])) 
        {
            self::$options[$name] = $value;
            return;
        }
        
        throw new DBHelper_Exception(
            sprintf('Cannot set unknown option [%s].', \AppUtils\parseVariable($value)->toString()),
            null,
            self::ERROR_CANNOT_SET_UNKNOWN_OPTION
        );
    }
    
    protected static function getOption(string $name)
    {
        self::initOptions();
        
        if(isset(self::$options[$name])) {
            return self::$options[$name];
        }
        
        return null;
    }
    
    protected static function initOptions()
    {
        if(!isset(self::$options))
        {
            self::$options = self::getDefaultOptions();
        }
    }
}
    