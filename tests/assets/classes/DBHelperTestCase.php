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
            $info = \AppUtils\ConvertHelper::exception2info($e);
            echo $e->getDetails();
            
            throw $e;
        }
        
        return $db;
    }
    
}