<?php

use PHPUnit\Framework\TestCase;
use AppDB\DBHelper_Database;
use AppDB\DBHelper;
use AppDB\DBHelper_Exception;

abstract class DBHelper_TestCase extends TestCase
{
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
    
    protected $testProducts = array(
        array('label' => 'Product one', 'price' => '100'),
        array('label' => 'Product two', 'price' => '14.99'),
        array('label' => 'Product three', 'price' => '60'),
        array('label' => 'Product foo with bar', 'price' => '1000'),
    );
    
    protected function insertTestData()
    {
        DBHelper::startTransaction();
        
        DBHelper::truncate('products');
        
        foreach($this->testProducts as $data) {
            DBHelper::insertDynamic('products', $data);
        }
        
        DBHelper::commitTransaction();
    }
    
    protected function clearTestData()
    {
        DBHelper::startTransaction();
        DBHelper::truncate('products');
        DBHelper::commitTransaction();
    }
}
