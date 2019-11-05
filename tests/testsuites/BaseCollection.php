<?php

use PHPUnit\Framework\TestCase;
use AppDB\DBHelper;
use AppDB\DBHelper_Exception;
use AppDB\DBHelper_BaseCollection;

final class BaseCollectionTest extends TestCase
{
    protected function setUp() : void
    {
        DBHelper::reset();
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
}
