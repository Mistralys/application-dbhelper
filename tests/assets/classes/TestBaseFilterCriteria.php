<?php

class TestBaseFilterCriteria extends \AppDB\DBHelper_BaseFilterCriteria
{
    public function getTableAlias(): string
    {
        return 'prods';
    }
    
    protected function getSelect(): array
    {
        return array(
            'product_id'
        );
    }
    
    public function getTableName(): string
    {
        return 'products';
    }
    
    protected function getSearchFields(): array
    {
        return array(
            'label'
        );
    }
    
    protected function prepareQuery()
    {
        
    }
}
