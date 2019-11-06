<?php

class TestBaseCollection extends \AppDB\DBHelper_BaseCollection
{
    public function getRecordClassName()
    {
        return 'TestBaseRecord';
    }

    public function getRecordSearchableColumns()
    {
        return array(
            'label' => t('Label')
        );
    }

    public function getCollectionLabel()
    {
        return t('Products');
    }

    public function getRecordLabel()
    {
        return t('Product');
    }

    public function getRecordDefaultSortKey()
    {
        return 'label';
    }

    public function getRecordTableName()
    {
        return 'products';
    }

    public function getRecordProperties()
    {
        return array(
            'label' => t('Label'),
            'price' => t('Price')
        );
    }

    public function getRecordFiltersClassName()
    {
        return TestFilterCriteria::class;
    }

    public function getRecordTypeName()
    {
        return 'product';
    }

    public function getRecordPrimaryName()
    {
        return 'product_id';
    }
}
