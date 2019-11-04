<?php
/**
 * File containing the {@link DBHelper_BaseCollection} class.
 * @package DBHelper
 * @see DBHelper_BaseCollection
 */

namespace AppDB;

/**
 * Base management class for a collection of database records
 * from the same table. Has methods to retrieve records, and
 * access information about records. 
 *
 * NOTE: Requires the primary key to be an integer auto_increment
 * column.
 *
 * This is meant to be extended, in conjunction with 
 * a custom record class based on the {@link DBHelper_BaseRecord}
 * class skeleton. Implement the abstract methods, and the
 * collection is ready to go.
 * 
 * @package DBHelper
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */
abstract class DBHelper_BaseCollection
{
    const ERROR_IDTABLE_SAME_TABLE_NAME = 16501;
    
    const ERROR_COLLECTION_HAS_NO_PARENT = 16502;
    
    const ERROR_BINDING_RECORD_NOT_ALLOWED = 16503;
    
    const ERROR_COLLECTION_ALREADY_HAS_PARENT = 16504;
    
    const ERROR_NO_PARENT_RECORD_BOUND = 16505;
    
    const SORT_DIR_ASC = 'ASC';
    
    const SORT_DIR_DESC = 'DESC';
    
    abstract public function getRecordClassName();
    
    abstract public function getRecordFiltersClassName();
    
    abstract public function getRecordDefaultSortKey();
    
   /**
    * Retrieves the searchable columns as an associative array
    * with column name => human readable label pairs.
    * 
    * @return array[string]string
    */
    abstract public function getRecordSearchableColumns();
    
    abstract public function getRecordTableName();
    
    abstract public function getRecordPrimaryName();
    
    abstract public function getRecordTypeName();
    
    abstract public function getCollectionLabel();
    
    abstract public function getRecordLabel();
    
    abstract public function getRecordProperties();
    
    public function getRecordDefaultSortDir()
    {
        return self::SORT_DIR_ASC;
    }
    
    public function getParentCollectionClass()
    {
        return null;
    }
    
    public function hasParentCollection()
    {
        return $this->getParentCollectionClass() !== null;
    }
    
    protected $recordIDTable;
    
    protected $recordClassName;
    
    protected $recordSortKey;
    
    protected $recordSortDir;
    
   /**
    * @var DBHelper_BaseRecord
    */
    protected $dummyRecord;
    
    protected $recordFiltersClassName;
    
    protected $recordFilterSettingsClassName;
    
    protected $instanceID;
    
    protected $requiresParent = false;
    
   /**
    * @var \AppUtils\Request
    * @see DBHelper_BaseCollection::getByRequest()
    */    
    protected $request;
    
   /**
    * NOTE: classes extending this class may not create
    * constructors with parameters. The interface must
    * stay parameter-less.
    */
    public function __construct()
    {
        $this->instanceID = nextJSID();
        $this->recordClassName = $this->getRecordClassName();
        $this->recordSortDir = $this->getRecordDefaultSortDir();
        $this->recordSortKey = $this->getRecordDefaultSortKey();
        $this->recordFiltersClassName = $this->getRecordFiltersClassName();
        $this->recordPrimaryName = $this->getRecordPrimaryName();
        $this->recordTable = $this->getRecordTableName();
        $this->requiresParent = $this->hasParentCollection();
    }
    
   /**
    * @var DBHelper_BaseRecord
    */
    protected $parentRecord;
    
    public function bindParentRecord(DBHelper_BaseRecord $record)
    {
        if(isset($this->parentRecord)) {
            throw new DBHelper_Exception(
                'Record already bound',
                sprintf(
                    'Cannot bind record [%s, ID %s], already bound to record [%s, ID %s].',
                    get_class($record),
                    $record->getID(),
                    get_class($this->parentRecord),
                    $this->parentRecord->getID()
                ),
                self::ERROR_COLLECTION_ALREADY_HAS_PARENT
            );
        }
        
        if($this->hasParentCollection()) {
            $this->parentRecord = $record; 
            $this->setForeignKey($record->getRecordPrimaryName(), $record->getID());
            return;
        }
        
        throw new DBHelper_Exception(
            'Binding a record is not allowed',
            sprintf(
                'The collection [%s] is not configured as a subcollection, and thus cannot be bound to a specific record. Tried binding to a [%s].',
                get_class($this),
                get_class($record)
            ),
            self::ERROR_BINDING_RECORD_NOT_ALLOWED
        );
    }
    
   /**
    * This is only available if the collection has a parent collection.
    * 
    * @return DBHelper_BaseRecord
    */
    public function getParentRecord()
    {
        return $this->parentRecord;
    }
    
    public function getInstanceID()
    {
        return $this->instanceID;
    }
    
    protected function getParentCollection()
    {
        if(!$this->hasParentCollection()) {
            throw new DBHelper_Exception(
                'Collection has no parent collection',
                sprintf(
                    'The collection [%s] has no parent collection.',
                    get_class($this)
                ),
                self::ERROR_COLLECTION_HAS_NO_PARENT
            );
        }
    }
    
    protected $foreignKeys = array();
    
   /**
    * Sets a foreign key/column that should be included in all queries.
    * This is supposed to be used internally in the constructor as needed.
    * 
    * @param string $name
    * @param string $value
    * @return DBHelper_BaseCollection
    */
    protected function setForeignKey($name, $value)
    {
        $this->foreignKeys[$name] = $value;
        return $this;
    }
    
   /**
    * Retrieves the foreign keys that should be included in
    * all queries, as an associative array with key => value pairs.
    * 
    * @return array
    */
    public function getForeignKeys()
    {
        return $this->foreignKeys;
    }
    
    public function getRecordSearchableKeys()
    {
        $columns = $this->getRecordSearchableColumns();
        return array_keys($columns);
    }
    
    public function getRecordSearchableLabels()
    {
        $columns = $this->getRecordSearchableColumns();
        return array_values($columns);
    }
    
   /**
    * @var DBHelper_BaseRecord[]
    */
    protected $records = array();
    
   /**
    * Retrieves a record by its ID.
    * 
    * @param integer $record_id
    * @return DBHelper_BaseRecord
    */
    public function getByID($record_id)
    {
        if(isset($this->records[$record_id])) {
            return $this->records[$record_id];
        }
        
        $this->checkParentRecord();

        $record = new $this->recordClassName($record_id, $this);
        $this->records[$record_id] = $record;
        
        return $record;
    }

    protected function checkParentRecord()
    {
        if($this->requiresParent !== true) {
            return;
        }
        
        if($this->parentRecord !== null) {
            return;
        }
        
        throw new DBHelper_Exception(
            'No parent record bound',
            sprintf(
                'Collections of type [%s] need a parent record to be set.',
                get_class($this)
            ),
            self::ERROR_NO_PARENT_RECORD_BOUND
        );
    }
    
   /**
    * Attempts to retrieve a record by its ID as specified in the request.
    * @return DBHelper_BaseRecord|NULL
    */
    public function getByRequest()
    {
        if(!isset($this->request)) 
        {
            $this->request = new \AppUtils\Request();
            
            $this->request->registerParam($this->getRecordPrimaryName())
            ->setInteger()
            ->setCallback(array($this, 'idExists'));
        }
        
        $record_id = $this->request->getParam($this->getRecordPrimaryName());
        
        if($record_id) {
            return $this->getByID($record_id);
        }
        
        return null;
    }
    
   /**
    * Retrieves a single record by a specific record key.
    * Note that if the key is not unique, the first one
    * in the result set is used, using the default sorting
    * key.
    * 
    * @param string $key
    * @param string $value
    * @return DBHelper_BaseRecord|NULL
    */
    public function getByKey($key, $value)
    {
        if($key == $this->recordPrimaryName) {
            return $this->getByID($value);
        }
        
        $where = $this->foreignKeys;
        $where[$key] = $value;
        
        $query = sprintf(
            "SELECT
                `%s`
            FROM
                `%s`
            WHERE
                %s
            ORDER BY
                `%s` %s
            LIMIT 
                0,1",
            $this->recordPrimaryName,
            $this->recordTable,
            DBHelper::buildWhereFieldsStatement($where),
            $this->recordSortKey,
            $this->recordSortDir
        );
        
        $id = DBHelper::fetchKey(
            $this->recordPrimaryName,
            $query,
            $where
        );
        
        if(empty($id)) {
            return null;
        }
        
        return $this->getByID($id);
    }
    
   /**
    * Checks whether a record with the specified ID exists in the database.
    * 
    * @param integer $record_id
    * @return boolean
    */
    public function idExists($record_id)
    {
        if(isset($this->records[$record_id])) {
            return true;
        }
        
        $where = $this->foreignKeys;
        $where[$this->recordPrimaryName] = $record_id;
        
        $query = sprintf( 
            "SELECT
                `%s`
            FROM
                `%s`
            WHERE
                %s",
            $this->recordPrimaryName,
            $this->recordTable,
            DBHelper::buildWhereFieldsStatement($where)
        );

        $id = DBHelper::fetchKey(
            $this->recordPrimaryName, 
            $query,
            $where
        );
        
        if($id !== null) {
            return true;
        }
        
        return false;
    }
    
    protected $recordPrimaryName;
    
    protected $recordTable;
    
   /**
    * Creates a dummy record of this collection, which can
    * be used to access the API that may not be available
    * statically.
    * 
    * @return DBHelper_BaseRecord
    */
    public function createDummyRecord()
    {
        if(isset($this->dummyRecord)) {
            return $this->dummyRecord;
        }
        
        $this->dummyRecord = $this->getByID(DBHelper_BaseRecord::DUMMY_ID);
        
        if(isset($this->recordIDTable) && $this->recordIDTable == $this->recordTable) {
            throw new DBHelper_Exception(
                'Duplicate DB collection tables',
                sprintf(
                    'The DBHelper collection [%s] has the same table [%s] defined as record table and ID table.',
                    get_class($this),
                    $this->recordIDTable
                ),
                self::ERROR_IDTABLE_SAME_TABLE_NAME
            );
        } 
        
        return $this->dummyRecord;
    }

   /**
    * Retrieves all records from the database, ordered by the default sorting key.
    * @return DBHelper_BaseRecord[]
    */
    public function getAll()
    {
        return $this->getFilterCriteria()->getItemsObjects();
    }
    
    public function countRecords()
    {
        return $this->getFilterCriteria()->countItems();
    }
    
   /**
    * Creates the filter criteria for this records collection, 
    * which is used to query the records.
    * 
    * @return DBHelper_BaseFilterCriteria
    */
    public function getFilterCriteria()
    {
        return new $this->recordFiltersClassName($this);
    }
    
   /**
    * Creates a new record with the specified data.
    * 
    * NOTE: This does not do any kind of validation,
    * you have to ensure that the required keys are
    * all present in the data set.
    * 
    * @param array $data
    * @return DBHelper_BaseRecord
    */
    public function createNewRecord($data=array())
    {
        $data = array_merge($data, $this->foreignKeys);
        
        DBHelper::requireTransaction('Create a new '.$this->getRecordTypeName());
        
        // use a special table for generating the record id?
        if(isset($this->recordIDTable)) 
        {
            $record_id = DBHelper::insert(sprintf(
                "INSERT INTO
                    `%s`
                SET `%s` = DEFAULT",
                $this->recordIDTable,
                $this->recordPrimaryName
            ));
            
            $data[$this->recordPrimaryName] = $record_id;
            
            DBHelper::insertDynamic(
                $this->recordTable,
                $data
            );
        } 
        else 
        {
            $record_id = DBHelper::insertDynamic(
                $this->recordTable, 
                $data
            );
        }
        
        $record = $this->getByID($record_id);
        $record->onCreated();
        
        return $record;
    }
    
    /**
     * Checks whether a specific column value exists 
     * in any of the collection's records.
     *
     * @param string $keyName
     * @param string $value
     * @return integer|boolean The record's ID, or false if not found.
     */
    public function recordKeyValueExists($keyName, $value)
    {
        $primary = $this->getRecordPrimaryName();
    
        $where = $this->foreignKeys;
        $where[$keyName] = $value;
        
        $query = sprintf(
            "SELECT
                `%s`
            FROM
                `%s`
            WHERE
                %s",
            $primary,
            $this->getRecordTableName(),
            DBHelper::buildWhereFieldsStatement($where)
        );
    
        $result = DBHelper::fetchKey(
            $primary,
            $query,
            $where
        );
    
        if(!empty($result)) {
            return $result;
        }
    
        return false;
    }
    
    protected function setIDTable($tableName)
    {
        $this->recordIDTable = $tableName;
    }
    
    public function deleteRecord(DBHelper_BaseRecord $record)
    {
        $record_id = $record->getID();
        
        $where = $this->foreignKeys;
        $where[$this->recordPrimaryName] = $record_id;

        if(isset($this->records[$record_id])) {
            unset($this->records[$record_id]);
        }
        
        DBHelper::deleteRecords(
            $this->recordTable,
            $where
        );
    }
    
    public function describe()
    {
        return array(
            'class' => get_class($this),
            'recordClassName' => $this->getRecordClassName(),
            'defaultSortDir' => $this->getRecordDefaultSortDir(),
            'defaultSortKey' => $this->getRecordDefaultSortKey(),
            'filtersClassName' => $this->getRecordFiltersClassName(),
            'filterSettingsClassName' => $this->getRecordFilterSettingsClassName(),
            'primaryName' => $this->getRecordPrimaryName(),
            'searchableColumns' => $this->getRecordSearchableColumns(),
            'searchableKeys' => $this->getRecordSearchableKeys(),
            'collectionLabel' => $this->getCollectionLabel(),
            'recordLabel' => $this->getRecordLabel(),
            'recordProperties' => $this->getRecordProperties(),
            'tableName' => $this->getRecordTableName(),
            'typeName' => $this->getRecordTypeName()
        );
    }
    
    protected $logPrefix;
    
    protected function log($message)
    {
        if(!isset($this->logPrefix)) {
            $this->logPrefix = ucfirst($this->getRecordTypeName()).' collection | ';
        }
        
        DBHelper::log($this->logPrefix.$message);
    }
}