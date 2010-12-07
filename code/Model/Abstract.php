<?php

abstract class Cm_Mongo_Model_Abstract extends Mage_Core_Model_Abstract {

  /** @var boolean - Assume an object is new unless loaded or saved or manually set otherwise */
  protected $_isNewObject = TRUE;

  protected $_references = array();
  
  public function isNewObject($flag = -1)
  {
    if($flag !== -1) {
      $this->_isNewObject = (bool) $flag;
    }
    return $this->_isNewObject;
  }

  public function setData($key, $value)
  {
    // Load all data
    if(is_array($key)) {
      $this->_data = $key;
    }

    // Set one value
    else {
      $this->_data[$key] = $value;
    }
    return $this;
  }

  public function getData($key='', $index=null)
  {
    // Get all data
    if(''===$key) {
      if($this->isDirty() && isset($this->_data['_id'])) {
        $this->load($this->getId());
      }
      return $this->_data;
    }

    // Get one value
    return parent::getData($key, $index);
  }

  public function getReferencedObject($field)
  {
    if( ! isset($this->_references[$field])) {
      $model = $this->getResource()->getFieldMappings()->{$field}->model;
      $object = Mage::getModel($model)->load($this->getData($field));
      $this->_references[$field] = $object;
    }
    return $this->_references[$field];
  }
  
  public function setReferencedObject($field, $object)
  {
    $this->setData($field, $object->getId());
    $this->_references[$field] = $object;
  }
  
  public function getReferencedCollection($field)
  {
    if( ! isset($this->_references[$field])) {
      $model = $this->getResource()->getFieldMappings()->{$field}->model;
      $collection = Mage::getModel($model)->getCollection()->loadAll($this->getData($field));
      $this->_references[$field] = $collection;
    }
    return $this->_references[$field];
  }
  
  public function op($op, $key, $value)
  {
    if( ! isset($this->_operations[$op])) {
      $this->_operations[$op] = array();
    }
    $this->_operations[$op][$key] = $value;
    if(strpos($key,'.') === FALSE) {
      $this->flagDirty($key);
    }
    return $this;
  }
  
  public function getPendingOperations()
  {
    return $this->_operations;
  }

  public function resetPendingOperations()
  {
    $this->_operations = array();
    return $this;
  }

  public function unflagDirty($key = '')
  {
    if(''===$key) {
      $this->_dirty = array();
    } else {
      unset($this->_dirty[$key]);
    }
    return $this;
  }
 
  public function hasDataChanges()
  {
    if($this->_hasDataChanges) {
      return TRUE;
    }
    
    foreach($this->getData() as $key => $value) {
      if($value instanceof Cm_Mongo_Model_Embedded && $value->hasDataChanges()) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
}