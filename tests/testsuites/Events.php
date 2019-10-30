<?php

use AppDB\DBHelper;
use AppDB\DBHelper_Database;
use AppDB\DBHelper_Exception;
use AppDB\DBHelper_Event;
use AppDB\DBHelper_OperationTypes;

final class EventsTest extends DBHelper_TestCase
{
    protected $initCalled = false;
    
    protected $beforeWriteCalled = false;
    
   /**
    * @var DBHelper_Event
    * @see EventsTest::handleEvent_withParameters()
    */
    protected $event;
    
   /**
    * @var array
    * @see EventsTest::handleEvent_withParameters()
    */
    protected $eventParams;
    
    protected function setUp() : void
    {
        DBHelper::reset();
        
        $this->initCalled = false;
        $this->beforeWriteCalled = false;
        $this->event = null;
        $this->eventParams = null;
    }

   /**
    * Dummy event handler method to check if the 
    * Init event is called.
    * 
    * @see EventsTest::test_triggerInitEvent()
    */
    public function handleEvent_Init()
    {
        $this->initCalled = true;
    }
    
   /**
    * Dummy event handler method to check if the
    * OnBeforeWriteOperation event is called.
    *
    * @see EventsTest::test_triggerBeforeWriteEvent()
    */
    public function handleEvent_OnBeforeWriteOperation()
    {
        $this->beforeWriteCalled = true;
    }
    
   /**
    * Event handler function used to keep track of the specified parameters.
    * @param \AppDB\DBHelper_Event $event
    * @param string $param1
    * @param string $param2
    */
    public function handleEvent_withParameters(\AppDB\DBHelper_Event $event, string $param1, string $param2)
    {
        $this->event = $event;
        $this->eventParams = array($param1, $param2);
    }
    
   /**
    * Ensure that a listener can be added as expected.
    */
    public function test_addListener()
    {
        $this->assertEquals(false, DBHelper::hasListener('Init'));
        
        DBHelper::onInit(array($this, 'handleEvent_Init'));
        
        $this->assertEquals(true, DBHelper::hasListener('Init'));
    }
    
   /**
    * Ensure that a listener can be removed by its ID.
    */
    public function test_removeListener()
    {
        $id = DBHelper::onInit(array($this, 'handleEvent_Init'));
        
        $this->assertEquals(true, DBHelper::hasListener('Init'));
        
        DBHelper::removeListener($id);
        
        $this->assertEquals(false, DBHelper::hasListener('Init'));
    }
    
   /**
    * Ensure that multiple listeners get added as expected.
    */
    public function test_mutipleListeners()
    {
        $id1 = DBHelper::onInit(array($this, 'handleEvent_Init'));
        $id2 = DBHelper::onInit(array($this, 'handleEvent_Init'));
        
        $ids = DBHelper::getListenerIDs('Init');
        
        $this->assertEquals(2, count($ids), 'There should be exactly two listener IDs.');
    }
    
   /**
    * Ensure that the Init event is triggered as expected.
    */
    public function test_triggerInitEvent()
    {
        $this->configureTestDatabase();
        
        $id = DBHelper::onInit(array($this, 'handleEvent_Init'));
        
        DBHelper::init();
        
        $this->assertTrue($this->initCalled, 'The Init handler should have been called.');
    }
    
   /**
    * Ensure that the before write event is triggered
    * when a write operation is executed.
    */
    public function test_triggerBeforeWriteEvent()
    {
        $this->configureTestDatabase();
        
        $id = DBHelper::onBeforeWriteOperation(array($this, 'handleEvent_OnBeforeWriteOperation'));
        
        DBHelper::init();
        
        DBHelper::startTransaction();
        
        DBHelper::insertDynamic(
            'products', 
            array(
                'label' => 'New product',
                'price' => 100
            )
        );
        
        $entries = DBHelper::fetchAll("SELECT * FROM products");

        DBHelper::rollbackTransaction();

        $this->assertEquals(1, count($entries), 'Should be exactly one entry in the table.');
        $this->assertTrue($this->beforeWriteCalled, 'The before write operation handler should have been called.');
    }
    
   /**
    * Ensure that a write operation can be cancelled using
    * the event API.
    */
    public function test_cancelBeforeWriteEvent()
    {
        $this->configureTestDatabase();
        
        $id = DBHelper::onBeforeWriteOperation(
            function(\AppDB\DBHelper_Event $event) 
            {
                $event->cancel('Cancel reason text');
            }
        );
        
        DBHelper::init();
        
        DBHelper::startTransaction();
        
        DBHelper::insertDynamic(
            'products',
            array(
                'label' => 'New product',
                'price' => 100
            )
        );
        
        $entries = DBHelper::fetchData('products');
        
        DBHelper::rollbackTransaction();
        
        $this->assertEquals(null, $entries, 'No record should have been inserted.');
    }
    
   /**
    * Ensure that specifying event parameters works as intended.
    */
    public function test_initEventParameters()
    {
        $params = array('foo', 'bar');
        
        DBHelper::onInit(
            array($this, 'handleEvent_withParameters'), 
            $params
        );
        
        $this->configureTestDatabase();
        
        DBHelper::init();
        
        $this->assertInstanceof(DBHelper_Event::class, $this->event, 'Should have stored an event instance.');
        $this->assertEquals('Init', $this->event->getName(), 'Should be the Init event.');
        $this->assertEquals($params, $this->eventParams, 'Should have passed the parameters through unchanged.');
        $this->assertEquals(array(), $this->event->getArguments(), 'The init event has no arguments.');
        $this->assertFalse($this->event->isWriteOperation(), 'Initialization is no write operation.');
        $this->assertEquals(null, $this->event->getType(), 'No operation type for the initialization.');
        $this->assertEquals(null, $this->event->getVariables(), 'No variables present for the initialization.');
    }
    
    /**
     * Ensure that specifying event parameters works as intended.
     */
    public function test_onWriteEventParameters()
    {
        $params = array('foo', 'bar');
        
        DBHelper::onBeforeWriteOperation(
            array($this, 'handleEvent_withParameters'),
            $params
        );
        
        $this->configureTestDatabase();
        
        DBHelper::init();
        
        DBHelper::startTransaction();
        
        $data = array(
            'label' => 'New product',
            'price' => 100
        );
        
        DBHelper::insertDynamic(
            'products',
            $data
        );
        
        DBHelper::rollbackTransaction();
        
        $this->assertInstanceof(DBHelper_Event::class, $this->event, 'Should have stored an event instance.');
        $this->assertEquals('BeforeDBWriteOperation', $this->event->getName(), 'Should be the BeforeDBWriteOperation event.');
        $this->assertEquals($params, $this->eventParams, 'Should have passed the parameters through unchanged.');
        $this->assertEquals(3, count($this->event->getArguments()), 'The OnBeforeWriteOperation should have 3 arguments.');
        $this->assertTrue($this->event->isWriteOperation(), 'This should be classified as a write operation.');
        $this->assertEquals(DBHelper_OperationTypes::TYPE_INSERT, $this->event->getType(), 'Should be an insert operation type.');
        $this->assertEquals($data, $this->event->getVariables(), 'Variables should be passed through unchanged.');
    }
}
