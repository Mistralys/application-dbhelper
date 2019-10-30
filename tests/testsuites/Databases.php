<?php

use PHPUnit\Framework\TestCase;

use AppDB\DBHelper;
use AppDB\DBHelper_Database;
use AppDB\DBHelper_Exception;

final class DatabasesTest extends TestCase
{
    protected function setUp() : void
    {
        DBHelper::reset();
    }
    
    public function test_addDatabase()
    {
        $db = DBHelper::addDatabase('foo', 'name');
        
        $this->assertInstanceOf(DBHelper_Database::class, $db, 'Database instance must be of DBHelper_Database class.');
        
        $this->assertEquals('foo', $db->getID());
        $this->assertEquals('name', $db->getName());
    }
    
    public function test_addDatabase_alreadyExists()
    {
        DBHelper::addDatabase('foo', 'name');
        
        $this->expectException(DBHelper_Exception::class);
        
        DBHelper::addDatabase('foo', 'name');
    }
    
    public function test_setPort()
    {
        $db = DBHelper::addDatabase('foo', 'name');
        
        $this->assertEquals(DBHelper_Database::DEFAULT_PORT, $db->getPort());
        
        $db->setPort(1234);
        
        $this->assertEquals(1234, $db->getPort());
    }
    
    public function test_setHost()
    {
        $db = DBHelper::addDatabase('foo', 'name');
        
        $this->assertEquals('localhost', $db->getHost());
        
        $db->setHost('127.0.0.1');
        
        $this->assertEquals('127.0.0.1', $db->getHost());
    }
    
    public function test_setCredentials()
    {
        $db = DBHelper::addDatabase('foo', 'name');
        
        $this->assertEquals('root', $db->getUsername());
        $this->assertEquals('', $db->getPassword());
        
        $db->setCredentials('name', 'pass');
    
        $this->assertEquals('name', $db->getUsername());
        $this->assertEquals('pass', $db->getPassword());
    }
    
    public function test_setInitCommand()
    {
        $db = DBHelper::addDatabase('foo', 'name');
        
        $this->assertEquals('', $db->getInitCommand());
        
        $db->setInitCommand('DO SOMETHING');
        
        $this->assertEquals('DO SOMETHING', $db->getInitCommand());
    }
}