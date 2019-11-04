<?php
/**
 * File containing the {@link DBHelper_BaseRecord} class.
 * 
 * @package DBHelper
 * @see DBHelper_BaseRecord
 */

namespace AppDB;

/**
 * Base container class for a single record in a database. 
 * Has a skeleton to retrieve information about the records
 * table, and can load and access record data. 
 *
 * @package DBHelper
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */
abstract class DBHelper_BaseRecord
{
    const ERROR_RECORD_DOES_NOT_EXIST = 13301;
    
    const ERROR_RECORD_KEY_UNKNOWN = 13302;
    
    const DUMMY_ID = 'dummy';
    
    protected $recordData;
    
    protected $recordTypeName;
    
    protected $recordTable;
    
    protected $recordPrimaryName;
    
    protected $isDummy = false;
    
   /**
    * @var DBHelper_BaseCollection
    */
    protected $collection;
    
    public function __construct($primary_id, DBHelper_BaseCollection $collection)
    {
        $this->collection = $collection;
        $this->recordTable = $collection->getRecordTableName();
        $this->recordPrimaryName = $collection->getRecordPrimaryName();
        $this->recordTypeName = $collection->getRecordTypeName();
        
        if($primary_id == self::DUMMY_ID) 
        {
            $this->isDummy = true;
            $this->recordData = array(
                $this->recordPrimaryName = self::DUMMY_ID
            );
            $this->recordKeys = array(
                $this->recordPrimaryName
            );
            return;
        }
        
        $where = $this->collection->getForeignKeys();
        $where[$this->recordPrimaryName] = $primary_id;
        
        $query = sprintf(
            "SELECT
                *
            FROM
                `%s`
            WHERE
                %s",
            $this->recordTable,
            DBHelper::buildWhereFieldsStatement($where)
        );
        
        $this->recordData = DBHelper::fetch(
            $query,
            $where
        );
        
        if(empty($this->recordData)) {
            throw new DBHelper_Exception(
                'Record not found',
                sprintf(
                    'Tried to retrieve a [%s] with primary id [%s] from table [%s].',
                    $this->recordTypeName,
                    $primary_id,
                    $this->recordTable
                ),
                self::ERROR_RECORD_DOES_NOT_EXIST
            );
        }    
        
        $this->recordKeys = array_keys($this->recordData);
        
        $this->init();
    }
    
    protected function init()
    {
        
    }

    public function getRecordTable()
    {
        return $this->recordTable;
    }
    
    public function getRecordPrimaryName()
    {
        return $this->recordPrimaryName;
    }
    
    public function getRecordTypeName()
    {
        return $this->recordTypeName;
    }
    
   /**
    * Whether this is a dummy record that is used only to
    * access information on this record type.
    * 
    * @return boolean
    */
    public function isDummy()
    {
        return $this->isDummy;
    }
    
   /**
    * Retrieves the collection used to access records like this.
    * @return DBHelper_BaseCollection
    */
    public function getCollection()
    {
        return $this->collection;
    }
    
    protected $recordKeys;
    
    public function countComtypes()
    {
        return DBHelper::fetchCount(
            "SELECT
                COUNT(`comtype_id`) AS `count`
            FROM
                `comtypes_variables`
            WHERE
                `oms_variable_id`=:oms_variable_id",
            array(
                'oms_variable_id' => $this->getID()
            )
        );
    }
    
    public function getID()
    {
        return $this->getRecordKey($this->recordPrimaryName);
    }
        
    public function getRecordKey($name, $default=null)
    {
        if(isset($this->recordData[$name])) {
            return $this->recordData[$name];
        }
        
        return $default;
    }
    
   /**
    * Retrieves a data key as an integer. Converts the value to int,
    * so beware using this on non-integer keys.
    * 
    * @param string $name
    * @param int $default
    * @return int|NULL
    */
    public function getRecordIntKey(string $name, int $default=null) : ?int
    {
        $value = $this->getRecordKey($name);
        if($value !== null && $value !== '') {
            return (int)$value;
        }
        
        return $default;
    }
    
   /**
    * Retrieves a data key, ensuring that it is a string.
    * 
    * @param string $name
    * @param string $default
    * @return string
    */
    public function getRecordStringKey(string $name, string $default='') : string
    {
        $value = $this->getRecordKey($name);
        if(!empty($value) && is_string($value)) {
            return $value;
        }
        
        return $default;
    }

   /**
    * Retrieves a data key as a DateTime object.
    * @param string $name
    * @param \DateTime $default
    * @return \DateTime|NULL
    */
    public function getRecordDateKey(string $name, \DateTime $default=null) : ?\DateTime
    {
        $value = $this->getRecordKey($name);
        if($value !== null) {
            return new \DateTime($value);
        }
        
        return $default;
    }
    
   /**
    * Treats a key as a string boolean value and returns 
    * the current value as a boolean.
    * 
    * @param string $name
    * @param boolean $default
    * @return boolean
    */
    protected function getRecordBooleanKey($name, $default=false) : bool
    {
        $value = $this->getRecordKey($name, $default);
        if($value===null) {
            $value = $default;
        }
        
        return \AppUtils\ConvertHelper::string2bool($value);
    }
    
    protected function recordKeyExists($name)
    {
        return in_array($name, $this->recordKeys);
    }
    
    protected $modified = array();
    
   /**
    * Converts a boolean value to its string representation to use
    * as internal value for a property. 
    * 
    * @param string $name
    * @param boolean|string $boolean A boolean, or string boolean representation.
    * @param boolean $yesno Whether to use the "yes/no" notation. Otherwise "true/false" is used.
    * @return boolean Whether the value has changed.
    */
    public function setRecordBooleanKey($name, $boolean, $yesno=true)
    {
        $value = \AppUtils\ConvertHelper::bool2string($boolean, $yesno);
        return $this->setRecordKey($name, $value);
    }
    
   /**
    * Sets the value of a data key of the record. If the data key has been
    * registered, the {@link recordKeyModified()} method is also called
    * to notify of changes. 
    * 
    * @param string $name
    * @param string $value
    * @return boolean
    */
    public function setRecordKey($name, $value)
    {
        if($this->isDummy) {
            return false;
        }
        
        $this->requireKey($name);
        
        $previous = $this->getRecordKey($name);
        if(\AppUtils\ConvertHelper::areStringsEqual($value, $previous)) {
            return false;
        }
        
        $this->recordData[$name] = $value;
        
        if(!in_array($name, $this->modified)) {
            $this->modified[] = $name;
            if(isset($this->registeredKeys[$name])) {
                $this->recordRegisteredKeyModified(
                    $name, 
                    $this->registeredKeys[$name]['label'], 
                    $this->registeredKeys[$name]['isStructural'], 
                    $previous, 
                    $value
                );
            }
        }
        
        return true;
    }
    
    protected function requireKey($name)
    {
        if($this->isDummy || $this->recordKeyExists($name)) {
            return;
        }
        
        throw new DBHelper_Exception(
            'Unknown record key',
            sprintf(
                'Cannot set key [%s] of [%s] record, it does not exist. Available keys are: [%s].',
                $name,
                $this->recordTypeName,
                implode(',', $this->recordKeys)
            ),
            self::ERROR_RECORD_KEY_UNKNOWN
        );
    }
    
   /**
    * Whether the record has been modified since the last save, or
    * the just the specified key.
    * 
    * @param string $key A single data key to check
    * @return boolean
    */
    public function isModified($key=null)
    {
        if($this->isDummy) {
            return false;
        }
        
        if(!empty($key) && $this->requireKey($key)) {
            return in_array($key, $this->modified);
        }
        
        return !empty($this->modified);
    }
    
   /**
    * Retrieves the names of all keys that have been modified since the last save.
    * @return string[]
    */
    public function getModifiedKeys()
    {
        return $this->modified;
    }
    
   /**
    * Saves all changes in the record. Only the modified keys
    * are saved each time using the internal changes tracking.
    * 
    * @return boolean Whether there was anything to save.
    */
    public function save()
    {
        if(!$this->isModified()) {
            return false;
        }
        
        DBHelper::requireTransaction(sprintf('Save %s record [%s]', $this->recordTypeName, $this->getID()));
        
        $data = $this->recordData;
        
        $sets = array();
        $keys = array_keys($this->recordData);
        foreach($keys as $key) {
            if($key == $this->recordPrimaryName || !$this->isModified($key)) {
                continue;
            }
            
            $sets[] = sprintf(
                "`%s`=:%s",
                $key,
                $key
            );
        }
        
        $where = $this->collection->getForeignKeys();
        $where[$this->recordPrimaryName] = $this->getID();
        
        $query = sprintf(
            "UPDATE
                `%s`
            SET
                %s
            WHERE
                %s",
            $this->recordTable,
            implode(',', $sets),
            DBHelper::buildWhereFieldsStatement($where)
        );
        
        $data = array_merge($this->recordData, $where);
        
        DBHelper::update($query, $data);
        
        $this->modified = array();
        
        return true;
    }
    
    protected function fixUTF8($columns)
    {
        foreach($this->recordData as $key => $value) {
            if(in_array($key, $columns)) {
                $this->recordData[$key] = \AppUtils\ConvertHelper::string2utf8($value);
            }
        }
    }
    
    protected function getWhereKeys($customKeys=array())
    {
        $where = $this->collection->getForeignKeys();
        $where[$this->recordPrimaryName] = $this->getID();
        return array_merge($customKeys, $where);
    }
    
    protected $registeredKeys = array();
    
   /**
    * Registers a record key, to enable tracking changes made to its value.
    * Whenever a registered key is modified, the {@link recordRegisteredKeyModified()}
    * method is called.
    * 
    * This is usually called in the record's {@link init()} method.
    * 
    * @param string $name The name of the key (of the database column)
    * @param string $label Human readable label of the key
    * @param boolean $isStructural Whether changing this key means it's a structural (critical) change
    */
    protected function registerRecordKey($name, $label, $isStructural=false)
    {
        $this->registeredKeys[$name] = array(
            'label' => $label,
            'isStructural' => $isStructural
        );
    }
    
   /**
    * This gets called whenever the value of a data key registered 
    * with {@link registerRecordKey()} is modified. Use this to handle
    * these changes automatically as needed, for example to add changelog
    * entries.
    *  
    * @param string $name Name of the data key
    * @param string $label Human readable label of the key
    * @param boolean $isStructural Whether changing this key means it's a structural (critical) change
    * @param string $oldValue The previous value
    * @param string $newValue The new value
    */
    abstract protected function recordRegisteredKeyModified($name, $label, $isStructural, $oldValue, $newValue);
    
   /**
    * Retrieves the record's parent record: this is only
    * available if the record's collection has a parent
    * collection.
    * 
    * @return DBHelper_BaseRecord|NULL
    */
    public function getParentRecord()
    {
        return $this->collection->getParentRecord();
    }
    
   /**
    * This is called once when the record has been created, 
    * and allows the record to run any additional initializations
    * it may need.
    */
    public function onCreated()
    {
    }
}