<?php
/**
 * Main bootstrapper used to set up the testsuites environment.
 * 
 * @package Application Utils
 * @subpackage Tests
 * @author Sebastian Mordziol <s.mordziol@mistralys.eu>
 */

    /**
     * The tests root folder (this file's location)
     * @var string
     */
    define('TESTS_ROOT', __DIR__ );

    require_once TESTS_ROOT.'/../vendor/autoload.php';
    
    require_once TESTS_ROOT.'/assets/classes/DBHelperTestCase.php';
    
    $localDBConfig = TESTS_ROOT.'/database-local.php';
    
    if(file_exists($localDBConfig))
    {
        require_once $localDBConfig;
    }
    else 
    {
        define('TESTS_DB_HOST', 'localhost');
        define('TESTS_DB_NAME', 'dbhelper_tests');
        define('TESTS_DB_USER', 'root');
        define('TESTS_DB_PASS', '');
    }