<?php

use PHPUnit\Framework\TestCase;
use AppDB\DBHelper;
use AppDB\DBHelper_Exception;
use AppDB\DBHelper_BaseCollection;

final class BaseRecordTest extends DBHelper_TestCase
{
    protected function setUp() : void
    {
        $this->insertTestData();
    }
    
    protected function tearDown() : void
    {
        $this->clearTestData();
    }
    
   /**
    * Test creating a record instance with a primary ID that does not exist in the database.
    */
    public function test_noMatchingDBRecord()
    {
        $collection = $this->createCollection();
        
        $this->expectException(DBHelper_Exception::class);
        
        new TestBaseRecord(PHP_INT_MAX, $collection);
    }
    
   /**
    * Check whether the collection returned matches the one passed.
    */
    public function test_getCollection()
    {
        $collection = $this->createCollection();
        
        $id = $this->getRecordID();
        
        $record = new TestBaseRecord($id, $collection);
        
        $this->assertEquals($collection, $record->getCollection());
    }
    
   /**
    * Check that the ID matches.
    */
    public function test_getID()
    {
        $collection = $this->createCollection();
        
        $id = $this->getRecordID();
        
        $record = $collection->getByID($id);
        
        $this->assertEquals($id, $record->getID());
    }
    
   /**
    * Check that the modified keys are tracked correctly.
    */
    public function test_getModifiedKeys()
    {
        $record = $this->getTestRecord();
        
        $this->assertEmpty($record->getModifiedKeys(), 'Should not be any modified keys at startup.');
        
        $record->setLabel('New test label');
        $this->assertEquals(array('label'), $record->getModifiedKeys());
        
        DBHelper::startTransaction();
        
            $this->assertTrue($record->save(), 'Saving should return true because a key has been modified');
        
        DBHelper::rollbackTransaction();
        
        $this->assertEmpty($record->getModifiedKeys(), 'Saving should reset the modified keys.');
    }
    
   /**
    * Check that the isModified method works as intended, both
    * with and without key name parameter.
    */
    public function test_isModified()
    {
        $record = $this->getTestRecord();
        
        $this->assertFalse($record->isModified(), 'Should not be any modified keys at startup.');
        
        $record->setLabel('New test label');
        $this->assertTrue($record->isModified(), 'Setting the label should enable the modified status.');
        $this->assertTrue($record->isModified('label'), 'Label should be marked as modified.');
        
        DBHelper::startTransaction();

            $this->assertTrue($record->save(), 'Saving should return true because a key has been modified');
            $this->assertFalse($record->isModified('label'), 'The label should be set as modified.');
        
        DBHelper::rollbackTransaction();
        
        $this->assertFalse($record->isModified(), 'Saving should reset the modified status.');
    }
    
   /**
    * Ensures that the record has the correct record configuration.
    */
    public function test_getTableDetails()
    {
        $record = $this->getTestRecord();
        
        $this->assertEquals('product_id', $record->getRecordPrimaryName(), 'The primary key name should match.');
        $this->assertEquals('products', $record->getRecordTable(), 'The table name should match.');
        $this->assertEquals('product', $record->getRecordTypeName(), 'The type of record should match.');
    }
    
   /**
    * Checks the return values of the strong typed data key
    * retrieval methods.
    */
    public function test_getTypedKeys()
    {
        $record = $this->getTestRecord();
        
        // STRINGS
        $this->assertIsString($record->getRecordStringKey('label'));
        $this->assertIsString($record->getRecordStringKey('unknown_key_name'));
        $this->assertEquals('foo', $record->getRecordStringKey('unknown_key_name', 'foo'));
        
        // INTEGERS
        $this->assertIsInt($record->getRecordIntKey('product_id'));
        $this->assertIsInt($record->getRecordIntKey('unknown_key_name'));
        $this->assertEquals($record->getRecordIntKey('unknown_key_name', 100), 100);
        
        // DATES
        $defaultDate = new DateTime();
        $this->assertInstanceOf(DateTime::class, $record->getRecordDateKey('date_added'));
        $this->assertNull($record->getRecordDateKey('unknown_key_name'));
        $this->assertEquals($defaultDate, $record->getRecordDateKey('unknown_key_name', $defaultDate));
        
        // BOOLEANS
        $this->assertIsBool($record->getRecordBooleanKey('in_stock'));
        $this->assertIsBool($record->getRecordBooleanKey('unknown_key_name'));
        $this->assertTrue($record->getRecordBooleanKey('unknown_key_name', true));
    }
    
   /**
    * Checks that the isDummy() method works as intended only for actual dummy records.
    */
    public function test_isDummy()
    {
        $record = $this->getTestRecord();
        
        $this->assertFalse($record->isDummy());
        
        $collection = $record->getCollection();
        $dummy = $collection->createDummyRecord();
        
        $this->assertTrue($dummy->isDummy());
    }
    
   /**
    * Ensure that the onCreated method of the record
    * gets called when it is creatred.
    * 
    * @see TestBaseRecord::onCreated()
    */
    public function test_event_onCreated()
    {
        $collection = $this->createCollection();

        DBHelper::startTransaction();
        
        $record = $collection->createNewRecord(array(
            'label' => 'OnCreated test',
            'price' => 20
        ));
        
        DBHelper::rollbackTransaction();
        
        $this->assertTrue($record->hasOnCreatedBeenCalled());
    }
    
   /**
    * Test that setting record keys is variable type independent.
    */
    public function test_setRecordKey()
    {
        $record = $this->getTestRecord();
        
        $tests = array(
            array(
                'label' => 'String value',
                'value' => 'FooBar',
                'expected' => 'FooBar'
            ),
            array(
                'label' => 'Int value',
                'value' => 120,
                'expected' => 120
            ),
            array(
                'label' => 'Boolean value',
                'value' => false,
                'expected' => false
            )
        );
        
        foreach($tests as $test)
        {
            $record->setRecordKey('label', $test['value']);
            $this->assertEquals($test['expected'], $record->getRecordKey('label'), $test['label']);
        }
    }
    
   /**
    * Ensure that setting non scalar keys throws an exception.
    */
    public function test_setRecordKey_nonScalar()
    {
        $record = $this->getTestRecord();
        
        $this->expectException(\AppUtils\ConvertHelper_Exception::class);
        
        $record->setRecordKey('label', new stdClass());
        
        
    }
   /**
    * Ensure that trying to set an unknown key triggers an exception.
    */
    public function test_setRecordKey_unknownKey()
    {
        $record = $this->getTestRecord();
        
        $this->expectException(DBHelper_Exception::class);
        
        $record->setRecordKey('unknown_key_name', 'value');
    }
    
   /**
    * Check setting boolean keys.
    */
    public function test_setRecordBooleanKey()
    {
        $record = $this->getTestRecord();
        
        $tests = array(
            array(
                'label' => 'Boolean false',
                'value' => false,
                'expected' => false
            ),
            array(
                'label' => 'Boolean true',
                'value' => true,
                'expected' => true
            ),
            array(
                'label' => 'Boolean string false',
                'value' => 'false',
                'expected' => false
            ),
            array(
                'label' => 'Boolean string true',
                'value' => 'true',
                'expected' => true
            ),
            array(
                'label' => 'Boolean string no',
                'value' => 'no',
                'expected' => false
            ),
            array(
                'label' => 'Boolean string yes',
                'value' => 'yes',
                'expected' => true
            )
        );

        foreach($tests as $test)
        {
            $record->setRecordBooleanKey('in_stock', $test['value']);
            $this->assertEquals($test['expected'], $record->getRecordBooleanKey('in_stock'), $test['label']);
        }
    }
}