<?php

abstract class Cm_Mongo_Model_Abstract extends Mage_Core_Model_Abstract {

  /** @var boolean - Assume an object is new unless loaded or saved or manually set otherwise */
  protected $_isNewObject = TRUE;

  protected $_references = array();
  
  protected $_parent = NULL;
  
  protected $_children = array();
  
  public function isNewObject($flag = -1)
  {
    if($flag !== -1) {
      $this->_isNewObject = (bool) $flag;
    }
    return $this->_isNewObject;
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
    return parent::getData($key == 'id' ? '_id' : $key, $index);
  }

  public function setData($key, $value=null)
  {
    $this->_hasDataChanges = true;
    
    // Load all data
    if(is_array($key)) {
      if(isset($key['id'])) {
        $key['_id'] = $key['id'];
        unset($key['id']);
      }
      $this->_data = $key;
    }

    // Set one value
    else {
      if($key == 'id') $key = '_id';
      $this->_data[$key] = $value;
    }
    return $this;
  }

  protected function _getFieldModel($field)
  {
    $model = $this->getResource()->getFieldMappings()->{$field}->model;
    $object = Mage::getModel($model);
    return $object;
  }
  
  public function _getEmbeddedObject($field)
  {
    if( ! $this->hasData($field)) {
      $object = $this->_getFieldModel($field);
      $this->_setEmbeddedObject($field, $object);
    }
    else if( ! $this->getData($field) instanceof Cm_Mongo_Model_Abstract) {
      throw new Exception('Expected data to be an embedded object.');
    }
    return $this->getData($field);
  }
  
  public function _setEmbeddedObject($field, Cm_Mongo_Model_Abstract $object)
  {
    $object->setEmbeddedIn($this);
    $this->setData($field, $object);
    $this->_children[] = $object;
    return $this;
  }
  
  public function setEmbeddedIn($parent)
  {
    $this->_parent = $parent;
    return $this;
  }
  
  public function getEmbeddedIn()
  {
    return $this->_parent;
  }
  
  public function getReferencedObject($field)
  {
    if( ! isset($this->_references[$field])) {
      $object = $this->_getFieldModel($field)->load($this->getData($field));
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
    
    foreach($this->_children as $value) {
      if($value->hasDataChanges()) {
        return TRUE;
      }
    }
    return FALSE;
  }
  
  public function reset()
  {
    $this->setData(array());
    $this->setOrigData();
    $this->unflagDirty();
    $this->resetPendingOperations();
    $this->_hasDataChanges = FALSE;
    $this->_isNewObject = TRUE;
    $this->_references = array();
    $this->_parent = NULL;
    foreach($this->_children as $object) {
      $object->reset();
    }
  }
  
}