<?php

use PHPUnit\Framework\TestCase;

use AppDB\DBHelper;
use AppDB\DBHelper_Database;
use AppDB\DBHelper_Exception;
use AppDB\DBHelper_OperationTypes;

final class DBHelperTest extends DBHelper_TestCase
{
    protected function setUp() : void
    {
        DBHelper::reset();
    }
    
    public function test_init_noDatabasesAdded()
    {
        $this->expectException(DBHelper_Exception::class);
        
        DBHelper::init();
    }
    
   /**
    * Tests whether the helper's auto initialization 
    * works as intended.
    */
    public function test_autoInit()
    {
        $this->configureTestDatabase();
        
        $this->assertFalse(DBHelper::isInitialized());
        
        DBHelper::fetchTableNames();
        
        $this->assertTrue(DBHelper::isInitialized());
    }
    
   /**
    * This should also fail because no databases
    * were added, thanks to the auto init.
    */
    public function test_execute_noDatabasesAdded()
    {
        $this->expectException(DBHelper_Exception::class);
        
        DBHelper::fetchTableNames();
    }
    
   /**
    * Check the query tracking feature enabling and disabling.
    */
    public function test_queryTracking_enabling()
    {
        $this->assertEquals(false, DBHelper::isQueryTrackingEnabled());
        
        DBHelper::enableQueryTracking();
        
        $this->assertEquals(true, DBHelper::isQueryTrackingEnabled());
        
        DBHelper::disableQueryTracking();
        
        $this->assertEquals(false, DBHelper::isQueryTrackingEnabled());
    }
    
   /**
    * Check that the query tracking works.
    */
    public function test_queryTracking_trackedCount()
    {
        $this->configureTestDatabase();
        
        DBHelper::fetchTableNames();
        
        $this->assertEquals(0, DBHelper::countQueries(), 'Tracking OFF; No queries should be tracked.');
        $this->assertEquals(1, DBHelper::getQueryCount(), 'Regular query count should still work.');
        
        DBHelper::enableQueryTracking();
        
        DBHelper::fetchTableNames();
        
        $this->assertEquals(1, DBHelper::countQueries(), 'Tracking ON; A single query should have been tracked.');
        $this->assertEquals(2, DBHelper::getQueryCount(), 'Regular query count should also increase.');
    }
    
   /**
    * Check the enabling and disabling of the debugging.
    */
    public function test_debugging_enabling()
    {
        $this->assertEquals(false, DBHelper::isDebuggingEnabled());
        
        DBHelper::enableDebugging();
        
        $this->assertEquals(true, DBHelper::isDebuggingEnabled());
        
        DBHelper::disableDebugging();
        
        $this->assertEquals(false, DBHelper::isDebuggingEnabled());
    }
    
   /**
    * Check if turning on the debugging actually outputs something.
    */
    public function test_debugging_output()
    {
        $this->configureTestDatabase();
        
        DBHelper::enableDebugging();
        
        ob_start();
        DBHelper::fetchData('products');
        $output = ob_get_clean();
        
        $sqlPresent = strstr($output, 'SELECT') !== false;
        
        $this->assertTrue($sqlPresent, 'The output should contain a SELECT statement.');
    }

   /**
    * Test retrieving a tables list.
    */
    public function test_fetchTableNames()
    {
        $this->configureTestDatabase();
        
        $names = DBHelper::fetchTableNames();
        
        $this->assertEquals(array('products'), $names, 'There should be a single table in the database.');
    }
    
   /**
    * Ensure that the columnExists method works as intended.
    */
    public function tests_columnExists()
    {
        $this->configureTestDatabase();
        
        $this->assertTrue(DBHelper::columnExists('products', 'label'), 'Label column should exist.');
        $this->assertFalse(DBHelper::columnExists('unknown', 'label'), 'Unknown table; should not exist.');
        $this->assertFalse(DBHelper::columnExists('products', 'unkown'), 'Unknown column; should not exist.');
    }
    
    public function test_execute_booleanReturnValue()
    {
        $this->configureTestDatabase();
        
        $result = DBHelper::execute(
            DBHelper_OperationTypes::TYPE_SELECT, 
            "SELECT * FROM products",
            array(),
            false
        );
        
        $this->assertTrue($result);
        
        $result = DBHelper::execute(
            DBHelper_OperationTypes::TYPE_SELECT, 
            "INVALID SQL QUERY",
            array(),
            false
        );
        
        $this->assertFalse($result);
    }
}