<?php

use PHPUnit\Framework\TestCase;
use AppDB\DBHelper;
use AppDB\DBHelper_Exception;
use AppDB\DBHelper_BaseCollection;

final class BaseCollectionTest extends DBHelper_TestCase
{
    protected function setUp() : void
    {
        \AppLocalize\Localization::selectAppLocale('en_UK');
        
        DBHelper::reset();
        
        $this->configureTestDatabase();
        
        $this->insertTestData();
    }
    
    protected function tearDown() : void
    {
        $this->clearTestData();
    }
    
    protected function createCollection() : TestBaseCollection
    {
        return DBHelper::createCollection(TestBaseCollection::class);
    }
    
   /**
    * Tests creating collection instances. 
    */
    public function test_createCollection()
    {
        $collection = DBHelper::createCollection(TestBaseCollection::class);
        
        $this->assertInstanceOf(DBHelper_BaseCollection::class, $collection, 'Should be an instance of the base collection.');
        
        $instanceID = $collection->getInstanceID();
        
        $collection = DBHelper::createCollection(TestBaseCollection::class);
        
        $this->assertEquals($instanceID, $collection->getInstanceID(), 'Fetching the same collection again should return the same instance.');
    }
    
   /**
    * Ensure that trying to create a collection that does not extend
    * the base collection is not allowed.
    */
    public function test_createCollection_invalidClass()
    {
        $this->expectException(DBHelper_Exception::class);
        
        $collection = DBHelper::createCollection(TestBaseCollectionInvalid::class);
    }
    
    public function test_countRecords()
    {
        $collection = $this->createCollection();
        
        $result = $collection->countRecords();
        
        $this->assertEquals(count($this->testProducts), $result, 'Should match the amount of test products.');
    }
    
   /**
    * All write operations require an active transaction.
    */
    public function test_createNewRecord_transactionRequired()
    {
        $collection = $this->createCollection();
        
        $this->expectException(DBHelper_Exception::class);
        
        $record = $collection->createNewRecord(array(
            'label' => 'New product',
            'price' => 500
        ));
    }
    
   /**
    * Test creating a new record.
    */
    public function test_createNewRecord()
    {
        $collection = $this->createCollection();
        
        DBHelper::startTransaction();
        
        $record = $collection->createNewRecord(array(
            'label' => 'New product',
            'price' => 500
        ));
        
        DBHelper::rollbackTransaction();
        
        $this->assertInstanceOf(TestBaseRecord::class, $record);
    }
    
   /**
    * Test fetching a record by its ID.
    */
    public function test_getByID()
    {
        // fetch any of the product IDs from the database
        $id = DBHelper::fetchKey('product_id', "SELECT product_id FROM products");
        
        $this->assertNotEmpty($id);
        
        $collection = $this->createCollection();
        
        $record = $collection->getByID($id); 
        
        $this->assertInstanceOf(TestBaseRecord::class, $record);
        $this->assertEquals($id, $record->getID(), 'The item ID should match.');
    }
    
   /**
    * Test getting an instance of the collection's filter criteria.
    */
    public function test_getFilterCriteria()
    {
        $collection = $this->createCollection();
        
        $filters = $collection->getFilterCriteria();
        
        $this->assertInstanceOf(TestBaseFilterCriteria::class, $filters);
    }
    
   /**
    * Checking whether an ID exists. 
    */
    public function test_idExists()
    {
        // fetch any of the product IDs from the database
        $id = DBHelper::fetchKey('product_id', "SELECT product_id FROM products");
        
        $collection = $this->createCollection();
        
        $this->assertTrue($collection->idExists($id));
        $this->assertFalse($collection->idExists(PHP_INT_MAX));
    }
    
   /**
    * Check if getting a record by the request works.
    */
    public function test_getByRequest()
    {
        // fetch any of the product IDs from the database
        $id = DBHelper::fetchKey('product_id', "SELECT product_id FROM products");
        
        $collection = $this->createCollection();
        
        $_REQUEST['product_id'] = $id;
        
        $record = $collection->getByRequest();
        
        $this->assertInstanceOf(TestBaseRecord::class, $record);
    }

   /**
    * Check if fetching all products works as intended.
    */
    public function test_getAll()
    {
        $collection = $this->createCollection();
        
        $records = $collection->getAll();
        
        $this->assertEquals(count($this->testProducts), count($records), 'The amount of records should match.');
    }
}
