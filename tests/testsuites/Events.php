<?php

use AppDB\DBHelper;
use AppDB\DBHelper_Database;
use AppDB\DBHelper_Exception;

final class EventsTest extends DBHelper_TestCase
{
    protected $initCalled = false;
    
    protected function setUp() : void
    {
        DBHelper::reset();
        
        $this->initCalled = false;
    }

    public function test_addListener()
    {
        $this->assertEquals(false, DBHelper::hasListener('Init'));
        
        DBHelper::onInit(
            array($this, 'handleEvent_Init'),
            'data'
        );
        
        $this->assertEquals(true, DBHelper::hasListener('Init'));
    }
    
    public function test_removeListener()
    {
        $id = DBHelper::onInit(
            array($this, 'handleEvent_Init'),
            'data'
        );
        
        $this->assertEquals(true, DBHelper::hasListener('Init'));
        
        DBHelper::removeListener($id);
        
        $this->assertEquals(false, DBHelper::hasListener('Init'));
    }
    
    public function test_mutipleListeners()
    {
        $id1 = DBHelper::onInit(
            array($this, 'handleEvent_Init'),
            'data'
        );
        
        $id2 = DBHelper::onInit(
            array($this, 'handleEvent_Init'),
            'data'
        );
        
        $ids = DBHelper::getListenerIDs('Init');
        
        $this->assertEquals(2, count($ids), 'There should be exactly two listener IDs.');
    }
    
    public function test_triggerEvent()
    {
        $this->configureTestDatabase();
        
        $id = DBHelper::onInit(
            array($this, 'handleEvent_Init'),
            'data'
        );
        
        DBHelper::init();
        
        $this->assertTrue($this->initCalled, 'The Init handler should have been called.');
    }
    
    public function handleEvent_Init()
    {
        $this->initCalled = true;
    }
}