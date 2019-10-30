<?php

use PHPUnit\Framework\TestCase;

use AppDB\DBHelper;
use AppDB\DBHelper_Database;
use AppDB\DBHelper_Exception;

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
    
    public function test_enableQueryTracking()
    {
        $this->assertEquals(false, DBHelper::isQueryTrackingEnabled());
        
        DBHelper::enableQueryTracking(true);
        
        $this->assertEquals(true, DBHelper::isQueryTrackingEnabled());
        
        DBHelper::disableQueryTracking();
        
        $this->assertEquals(false, DBHelper::isQueryTrackingEnabled());
    }
    
    public function test_enableDebugging()
    {
        $this->assertEquals(false, DBHelper::isDebuggingEnabled());
        
        DBHelper::enableDebugging(true);
        
        $this->assertEquals(true, DBHelper::isDebuggingEnabled());
        
        DBHelper::disableDebugging();
        
        $this->assertEquals(false, DBHelper::isDebuggingEnabled());
    }

    public function test_fetchTableNames()
    {
        $this->configureTestDatabase();
        
        DBHelper::init();
        
        $names = DBHelper::fetchTableNames();
        
        $this->assertEquals(array('products'), $names, 'There should be a single table in the database.');
    }
    
}