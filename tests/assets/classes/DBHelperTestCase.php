<?php

use PHPUnit\Framework\TestCase;
use AppDB\DBHelper_Database;
use AppDB\DBHelper;
use AppDB\DBHelper_Exception;

abstract class DBHelper_TestCase extends TestCase
{
   /**
    * Configures the test database connection using the configuration
    * settings as specified in the bootstrap.php tests file.
    * 
    * @throws DBHelper_Exception
    * @return DBHelper_Database
    */
    protected function configureTestDatabase() : DBHelper_Database
    {
        $db = DBHelper::addDatabase('tests', TESTS_DB_NAME)
        ->setHost(TESTS_DB_HOST)
        ->setCredentials(TESTS_DB_USER, TESTS_DB_PASS);
        
        try
        {
            $db->connect();
        } 
        catch(DBHelper_Exception $e)
        {
            echo \AppUtils\ConvertHelper::exception2info($e)->toString();
            echo $e->getDetails();
            
            throw $e;
        }
        
        return $db;
    }

   /**
    * Fetches any of the test product IDs from the database.
    * Must have called the insertTestData() method for this
    * to work.
    * 
    * @return int
    */
    protected function getRecordID() : int
    {
        $id = DBHelper::fetchKey('product_id', "SELECT product_id FROM products");
        if($id !== null) {
            return (int)$id;
        }
        
        throw new Exception('Cannot get test record ID: no test records have been inserted.');
    }
    
    protected $testProducts = array(
        array('label' => 'Product one', 'price' => '100', 'in_stock' => 'yes', 'date_added' => '2019-06-01 12:00:00'),
        array('label' => 'Product two', 'price' => '14.99', 'in_stock' => 'no'),
        array('label' => 'Product three', 'price' => '60', 'in_stock' => 'no'),
        array('label' => 'Product foo with bar', 'price' => '1000', 'in_stock' => 'yes'),
    );
    
   /**
    * Resets the DBHelper and inserts all products from the
    * test products array into the database. Call clearTestData()
    * afterwards to remove them again.
    * 
    * @see DBHelper_TestCase::clearTestData()
    */
    protected function insertTestData()
    {
        \AppLocalize\Localization::selectAppLocale('en_UK');
        
        DBHelper::reset();
        
        $this->configureTestDatabase();
        
        DBHelper::startTransaction();
        
        DBHelper::truncate('products');
        
        foreach($this->testProducts as $data) {
            DBHelper::insertDynamic('products', $data);
        }
        
        DBHelper::commitTransaction();
    }
    
   /**
    * Removes all test products from the database. Has no 
    * effect if they have already been cleared.
    */
    protected function clearTestData()
    {
        DBHelper::startTransaction();
        DBHelper::truncate('products');
        DBHelper::commitTransaction();
    }

   /**
    * Creates an instance of the test base collection.
    * 
    * @return TestBaseCollection
    */
    protected function createCollection() : TestBaseCollection
    {
        return DBHelper::createCollection(TestBaseCollection::class);
    }
    
    protected function getTestRecord() : TestBaseRecord
    {
        $id = $this->getRecordID();
        
        $collection = $this->createCollection();
        
        return $collection->getByID($id);
    }
}
