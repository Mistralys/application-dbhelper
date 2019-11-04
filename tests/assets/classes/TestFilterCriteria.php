<?php

class TestFilterCriteria extends \AppDB\DBHelper_FilterCriteria
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
}
