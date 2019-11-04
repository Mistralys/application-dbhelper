<?php

use PHPUnit\Framework\TestCase;
use AppDB\DBHelper;
use AppDB\DBHelper_FilterCriteria;
use AppDB\DBHelper_Exception;

final class FilterCriteriaTest extends DBHelper_TestCase
{
    protected function setUp() : void
    {
        \AppLocalize\Localization::selectAppLocale('en_UK');
        
        DBHelper::reset();
        
        $this->configureTestDatabase();
        
        $this->insertTestData();
    }
    
    protected function tearDown() : void
    {
        $this->clearTestData();
    }
    
   /**
    * Test fetching all items without specifying any criteria.
    */
    public function test_countItems()
    {
        $filters = new TestFilterCriteria();
        
        $items = $filters->getItems();
        $total = count($this->testProducts);
        
        $this->assertEquals($total, count($items), 'Should be exactly the amount of test data entries.');
        $this->assertEquals($total, $filters->countItems(), 'Should be the same amount.');
    }
    
   /**
    * Test whether counting items unfiltered works even with active filters.
    */
    public function test_countItems_unfiltered()
    {
        $filters = new TestFilterCriteria();
        
        $filters->setSearch('foo AND bar');
        
        $items = $filters->getItems();
        $total = count($this->testProducts);
        
        $this->assertEquals(1, count($items), 'Should be exactly one entry.');
        $this->assertEquals($total, $filters->countUnfiltered(), 'Should be the total amount of test entries.');
    }

   /**
    * Test whether searching for a simple term works.
    */
    public function test_search_simple()
    {
        $filters = new TestFilterCriteria();
        
        $filters->setSearch('two');
        
        $items = $filters->getItems();
        
        $this->assertEquals(1, count($items), 'Should only match a single entry.');
    }

   /**
    * Test connecting search terms with OR.
    */
    public function test_search_or()
    {
        $filters = new TestFilterCriteria();
        
        $filters->setSearch('two OR three');
        
        $items = $filters->getItems();
        
        $this->assertEquals(2, count($items), 'Should be exactly two entries.');
    }
    
   /**
    * Test connecting search terms with AND.
    */
    public function test_search_and()
    {
        $filters = new TestFilterCriteria();
        
        $filters->setSearch('foo AND bar');
        
        $items = $filters->getItems();
        
        $this->assertEquals(1, count($items), 'Should be exactly one entry.');
    }
    
   /**
    * Test setting a LIMIT clause.
    */
    public function test_setLimit()
    {
        $filters = new TestFilterCriteria();
        
        $filters->setLimit(0, 1);
        
        $items = $filters->getItems();
        
        $this->assertEquals(1, count($items), 'Should be exactly one entry.');
    }
    
   /**
    * Check the different ways to set the order direction.
    */
    public function test_setOrderBy()
    {
        $filters = new TestFilterCriteria();
        
        $this->assertEquals(DBHelper_FilterCriteria::ORDER_ASCENDING, $filters->getSortOrder(), 'Initial sorting direction should match.');
        
        $filters->setOrderBy('label', DBHelper_FilterCriteria::ORDER_DESCENDING);
        
        $this->assertEquals(DBHelper_FilterCriteria::ORDER_DESCENDING, $filters->getSortOrder(), 'Sorting order should have been changed.');
        
        $filters->orderAscending();
        
        $this->assertEquals(DBHelper_FilterCriteria::ORDER_ASCENDING, $filters->getSortOrder(), 'Sorting order should have been changed.');
        
        $filters->orderDescending();
        
        $this->assertEquals(DBHelper_FilterCriteria::ORDER_DESCENDING, $filters->getSortOrder(), 'Sorting order should have been changed.');
    }
    
   /**
    * Ensure that validating the order direction works as intended.
    */
    public function test_setOrderBy_invalid()
    {
        $filters = new TestFilterCriteria();
        
        $this->expectException(DBHelper_Exception::class);
        
        $filters->setSortOrder('INVALID');
    }
    
   /**
    * Check that search term connectors get translated correctly.
    */
    public function test_getSearchTerms_translated()
    {
        $filters = new TestFilterCriteria();
        
        \AppLocalize\Localization::selectAppLocale('de_DE');
        
        $filters->setSearch('foo UND bar');
        
        $terms = $filters->getSearchTerms();
        
        $expected = array(
            'foo',
            'AND',
            'bar'
        );
        
        $this->assertEquals($expected, $terms, 'Should be an array.');
    }
}
