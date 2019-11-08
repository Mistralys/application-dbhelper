<?php

class TestBaseRecord extends \AppDB\DBHelper_BaseRecord
{
    protected $onCreatedCalled = false;
    
    protected function recordRegisteredKeyModified($name, $label, $isStructural, $oldValue, $newValue)
    {
        
    }
    
    public function setLabel(string $label) : TestBaseRecord
    {
        $this->setRecordKey('label', $label);
        return $this;
    }
    
    public function getLabel() : string
    {
        return $this->getRecordStringKey('label');
    }
    
    public function onCreated()
    {
        $this->onCreatedCalled = true;
    }

    public function hasOnCreatedBeenCalled() : bool
    {
        return $this->onCreatedCalled;
    }
}
    