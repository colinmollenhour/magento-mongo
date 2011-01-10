<?php

abstract class Cm_Mongo_Model_Abstract extends Mage_Core_Model_Abstract {

  /** @var string   Id field name is always _id */
  protected $_idFieldName = '_id';

  /** @var boolean   Assume an object is new unless loaded or saved or manually set otherwise */
  protected $_isObjectNew = TRUE;

  /** @var array   Storage for referenced objects */
  protected $_references = array();
  
  protected $_children;
  protected $_root;
  protected $_path;
  protected $_operations;
  
  /**
   * Get or set the _isObjectNew flag. Defaults to TRUE.
   * 
   * Overridden to allow NULL to be set/returned for upsert on save.
   * 
   * @param boolean $flag
   * @return boolean
   */
  public function isObjectNew($flag = -1)
  {
    if($flag !== -1) {
      $this->_isObjectNew = (bool) $flag;
    }
    return $this->_isObjectNew;
  }

  /**
   * Get some or all data.
   * 
   * Overridden to allow . delimited names to retrieve nested data and disable the second parameter.
   * 
   * @param string $key
   * @param int $index DO NOT USE
   * @return mixed 
   */
  public function getData($key='', $index=null)
  {
    if(''===$key) {
      return $this->_data;
    }
    if(strpos($key,'.')) {
      return parent::getData(str_replace('.','/',$key));
    }
    return isset($this->_data[$key]) ? $this->_data[$key] : NULL;
  }

  /**
   * Set data all at once, by key, or by nested key.
   *
   * Overridden to allow full arrays and nested values to be set. Otherwise,
   * adding a value to an array using setData would lose the proper order.
   *
   * @param mixed $key
   * @param mixed $value
   * @param mixed $_value
   * @return Cm_Mongo_Model_Abstract
   */
  public function setData($key, $value = NULL, $_value = NULL)
  {
    $this->_hasDataChanges = true;

    // Set all new data
    if(is_array($key)) {
      if($this->_origData) {
        foreach($key as $k => $v) {
          $this->setData($k, $v);
        }
      }
      else {
        $this->_data = $key;
      }
    }

    // Set one key to a value
    else if($_value === NULL) {
      // For arrays, unset the orig data so that the full value will be overwritten on update
      if(is_array($value) && isset($this->_origData[$key]) && $this->_origData[$key] != $value) {
        unset($this->_origData[$key]);
      }
      $this->_data[$key] = $value;
    }

    // Set a nested key to a value
    else {
      if( ! isset($this->_data[$key])) {
        $this->_data[$key] = array();
      }
      $this->_data[$key][$value] = $_value;
    }
    
    return $this;
  }

  /**
   * Unsets the data at the given key.
   * 
   * Overridden to add an $unset operation for when the object is saved.
   * 
   * @param string $key
   * @return Cm_Mongo_Model_Abstract
   */
  public function unsetData($key=null)
  {
    if( ! is_null($key) && $this->isObjectNew() === FALSE && ! isset($this->getResource()->getFieldMappings()->$key->required)) {
      $this->op('$unset', $key, 1);
    }
    return parent::unsetData($key);
  }
  
  /**
   * Loads data into the model using the resource hydration method.
   * 
   * @param array $data
   * @return Cm_Mongo_Model_Abstract 
   */
  public function loadData($data)
  {
    $this->getResource()->hydrate($this, $data, FALSE);
    return $this;
  }
  
  /**
   * Retrieve an embedded object using the schema info by key
   * 
   * @param string $field
   * @return Cm_Mongo_Model_Abstract
   */
  protected function _getEmbeddedObject($field)
  {
    if( ! $this->hasData($field) || $this->getData($field) === NULL) {
      $object = $this->getResource()->getFieldModel($field);
      $this->_setEmbeddedObject($field, $object);
    }
    else if( ! $this->getData($field) instanceof Cm_Mongo_Model_Abstract) {
      throw new Exception('Expected data to be an embedded object.');
    }
    return $this->getData($field);
  }
  
  /**
   * Set an embedded object by key
   * 
   * @param string $field
   * @param Cm_Mongo_Model_Abstract $object
   * @return Cm_Mongo_Model_Abstract 
   */
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
  
  /**
   * Set the parent object and the path relative to the parent.
   * 
   * Although this method is public it should not be used except by the _setEmbeddedObject method.
   * 
   * @param Cm_Mongo_Model_Abstract $parent
   * @param string $path
   * @return Cm_Mongo_Model_Abstract 
   */
  public function _setEmbeddedIn($parent, $path)
  {
    $this->_root = $parent->getRootObject();
    $this->_path = $path.'.';
    return $this;
  }
  
  /**
   * Get the root object (highest parent, or self if this is the parent object)
   * 
   * @return Cm_Mongo_Model_Abstract
   */
  public function getRootObject()
  {
    return isset($this->_root) ? $this->_root : $this;
  }
  
  /**
   * Get the full path of the given field relative to the root object.
   * 
   * @param string $field
   * @return string
   */
  public function getFieldPath($field = '')
  {
    return (isset($this->_path) ? $this->_path : '').$field;
  }
  
  /**
   * Get the referenced object. Attempts to load the object on first retrieval.
   * 
   * @param string $field
   * @return Cm_Mongo_Model_Abstract
   */
  public function getReferencedObject($field)
  {
    if( ! isset($this->_references[$field])) {
      $object = $this->getResource()->getFieldModel($field);
      if($this->hasData($field)) {
        $object->load($this->getData($field));
      }
      $this->_references[$field] = $object;
    }
    return $this->_references[$field];
  }
  
  /**
   * Sets the referenced object. Stores an internal reference and sets the field to the object's ID.
   * 
   * @param string $field
   * @param Cm_Mongo_Model_Abstract $object
   * @return Cm_Mongo_Model_Abstract 
   */
  public function setReferencedObject($field, Cm_Mongo_Model_Abstract $object)
  {
    $this->setData($field, $object->getId());
    $this->_references[$field] = $object;
    return $this;
  }
  
  /**
   * Get a collection of referenced objects
   * 
   * @param string $field
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function getReferencedCollection($field)
  {
    if( ! isset($this->_references[$field])) {
      $model = $this->getResource()->getFieldModel($field);
      $collection = Mage::getModel($model)->getCollection()->loadAll($this->getData($field));
      $this->_references[$field] = $collection;
    }
    return $this->_references[$field];
  }
  
  /**
   * Queue an operation to be performed during the next save.
   * 
   * @see http://www.mongodb.org/display/DOCS/Updating
   * 
   * If $key is an array, the operation will be applied to each key => value pair.
   * 
   * @param string $op
   * @param string $key
   * @param mixed $value
   * @return Cm_Mongo_Model_Abstract 
   */
  public function op($op, $key, $value = NULL)
  {
    // All operations are routed to the root object
    if(isset($this->_root)) {
      if(is_array($key)) {
        foreach($key as $k => $v) {
          $this->getRootObject()->op($op, $this->_path.$k, $v);
        }
      }
      else {
        $this->getRootObject()->op($op, $this->_path.$key, $value);
      }
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
    $this->_hasDataChanges = TRUE;
    return $this;
  }
  
  /**
   * Retrieve all pending operations. Only needs to be used by resource model.
   * 
   * @return array
   */
  public function getPendingOperations()
  {
    return isset($this->_operations) ? $this->_operations : array();
  }

  /**
   * Reset the pending operations. Only needs to be used by resource model.
   * 
   * @return Cm_Mongo_Model_Abstract 
   */
  public function resetPendingOperations()
  {
    unset($this->_operations);
    return $this;
  }
 
  /**
   * Overriden to check all embedded objects for data changes as well.
   * 
   * @return boolean
   */
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
  
  /**
   * When memory usage is important, use this to ensure there are no memory leaks.
   */
  public function reset()
  {
    $this->setData(array());
    $this->setOrigData();
    $this->resetPendingOperations();
    $this->_hasDataChanges = FALSE;
    $this->_isObjectNew = TRUE;
    $this->_references = array();
    unset($this->_root);
    if(isset($this->_children)) {
      foreach($this->_children as $object) {
        $object->reset();
      }
      unset($this->_children);
    }
  }
  
  /**
   * Overridden to not set an object as new when there is no id to support upserts.
   * 
   * @return Cm_Mongo_Model_Abstract 
   */
  protected function _beforeSave()
  {
    Mage::dispatchEvent('model_save_before', array('object'=>$this));
    Mage::dispatchEvent($this->_eventPrefix.'_save_before', $this->_getEventData());
    return $this;
  }
  
}