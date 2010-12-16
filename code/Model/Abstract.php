<?php

abstract class Cm_Mongo_Model_Abstract extends Mage_Core_Model_Abstract {

  /** @var boolean - Assume an object is new unless loaded or saved or manually set otherwise */
  protected $_isNewObject = TRUE;

  protected $_references = array();
  
  /* These property names are reserved but not declared
  protected $_children;
  protected $_root;
  protected $_path;
   */
  
  public function isNewObject($flag = -1)
  {
    if($flag !== -1) {
      $this->_isNewObject = (bool) $flag;
    }
    return $this->_isNewObject;
  }

  public function getData($key='', $index=null)
  {
    if(''===$key) {
      return $this->_data;
    }
    if('id'===$key) {
      $key = '_id';
    } else if(strpos($key,'.')) {
      return parent::getData(str_replace('.','/',$key));
    }
    return isset($this->_data[$key]) ? $this->_data[$key] : NULL;
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

  public function unsetData($key=null)
  {
    if( ! is_null($key) && $this->isNewObject() === FALSE && ! isset($this->getResource()->getFieldMappings()->$key->required)) {
      $this->op('$unset', $key, 1);
    }
    return parent::unsetData($key);
  }
  
  protected function _getEmbeddedObject($field)
  {
    if( ! $this->hasData($field)) {
      $object = $this->getResource()->getFieldModel($field);
      $this->_setEmbeddedObject($field, $object);
    }
    else if( ! $this->getData($field) instanceof Cm_Mongo_Model_Abstract) {
      throw new Exception('Expected data to be an embedded object.');
    }
    return $this->getData($field);
  }
  
  protected function _setEmbeddedObject($field, Cm_Mongo_Model_Abstract $object)
  {
    $object->_setEmbeddedIn($this, $this->getFieldPath($field));
    $this->setData($field, $object);
    if( ! isset($this->_children)) {
      $this->_children = array();
    }
    $this->_children[] = $object;
    return $this;
  }
  
  public function _setEmbeddedIn($parent, $path)
  {
    $this->_root = $parent->getRootObject();
    $this->_path = $path.'.';
    return $this;
  }
  
  public function getRootObject()
  {
    return isset($this->_root) ? $this->_root : $this;
  }
  
  public function getFieldPath($field = '')
  {
    return (isset($this->_path) ? $this->_path : '').$field;
  }
  
  public function getReferencedObject($field)
  {
    if( ! isset($this->_references[$field])) {
      $object = $this->getResource()->getFieldModel($field)->load($this->getData($field));
      $this->_references[$field] = $object;
    }
    return $this->_references[$field];
  }
  
  public function setReferencedObject($field, $object)
  {
    $this->setData($field, $object->getId());
    $this->_references[$field] = $object;
    return $this;
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
  
  public function op($op, $key, $value = NULL)
  {
    // All operations are routed to the root object
    if(isset($this->_root)) {
      $this->getRootObject()->op($op, $this->_path.$key, $value);
      return $this;
    }
    
    // Allow multiple operations if $key is an array
    if(is_array($key)) {
      foreach($key as $k => $v) {
        $this->op($op, $k, $v);
      }
      return $this;
    }
    
    // Save operation for later
    if( ! isset($this->_operations)) {
      $this->_operations = array();
    }
    if( ! isset($this->_operations[$op])) {
      $this->_operations[$op] = array();
    }
    $this->_operations[$op][$key] = $value;
    return $this;
  }
  
  public function getPendingOperations()
  {
    return isset($this->_operations) ? $this->_operations : array();
  }

  public function resetPendingOperations()
  {
    unset($this->_operations);
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
    
    if(isset($this->_children)) {
      foreach($this->_children as $value) {
        if($value->hasDataChanges()) {
          return TRUE;
        }
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
    unset($this->_root);
    if(isset($this->_children)) {
      foreach($this->_children as $object) {
        $object->reset();
      }
      unset($this->_children);
    }
  }
  
}