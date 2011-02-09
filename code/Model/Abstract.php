<?php
/**
 * Abstract model class, objectifies a document or embedded document.
 */
abstract class Cm_Mongo_Model_Abstract extends Mage_Core_Model_Abstract
{

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

  /** @var boolean  If enabled, a load will add the item to cache */
  protected $_isCacheEnabled = FALSE;

  /**
   * Get the resource instance. (overridden to improve IDE chaining)
   *
   * @return Cm_Mongo_Model_Resource_Abstract
   */
  public function getResource()
  {
    return $this->_getResource();
  }

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
        $this->_data = array();
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
      // For arrays and embedded collections, unset the orig data so that the full value will be overwritten on update
      if(
        (is_array($value) || $value instanceof Cm_Mongo_Model_Resource_Collection_Embedded) &&
        (isset($this->_origData[$key]) && $this->_origData[$key] != $value)
      ) {
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
      $object = Mage::getModel($this->getResource()->getFieldModelName($field));
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
      $this->_children = new ArrayObject();
    }
    $this->_children[$field] = $object;
    return $this;
  }

  /**
   * Retrieve an embedded collection using the schema info by key
   *
   * @param string $field
   * @return Cm_Mongo_Model_Resource_Collection_Embedded
   */
  protected function _getEmbeddedCollection($field)
  {
    if( ! $this->hasData($field) || $this->getData($field) === NULL) {
      $collection = new Cm_Mongo_Model_Resource_Collection_Embedded;
      $collection->setItemObjectClass($this->getResource()->getFieldModelName($field));
      $this->_setEmbeddedCollection($field, $collection);
    }
    else if( ! $this->getData($field) instanceof Cm_Mongo_Model_Resource_Collection_Embedded) {
      throw new Exception('Expected data to be an embedded collection.');
    }
    return $this->getData($field);
  }

  /**
   * Set an embedded collection by key, used by embeddedSet type
   *
   * @param string $field
   * @param Cm_Mongo_Model_Resource_Collection_Embedded $collection
   * @return Cm_Mongo_Model_Abstract
   */
  protected function _setEmbeddedCollection($field, Cm_Mongo_Model_Resource_Collection_Embedded $collection)
  {
    // Setup proper nesting for future items added
    $path = $this->getFieldPath($field);
    $collection->_setEmbeddedIn($this, $path);

    // Set proper nesting for existing items
    $i = 0;
    foreach($collection->getItems() as $item) {
      $item->_setEmbeddedIn($this, $path.'.'.$i);
      $i++;
    }

    // Set data and add collection to children for proper reset and hasDataChanges operation
    $this->setData($field, $collection);
    if( ! isset($this->_children)) {
      $this->_children = new ArrayObject();
    }
    $this->_children[$field] = $collection;
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
   * Unset an embedded object or collection
   *
   * @param string $field
   */
  protected function _unsetEmbeddedObject($field)
  {
    if($object = $this->getData($field)) {
      unset($this->_children[$field]);
      $object->reset();
      $this->unsetData($field);
    }
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
    if( ! isset($this->_references[$field]))
    {
      $modelName = $this->getResource()->getFieldModelName($field);

      // If there is a reference, load from cache or from database
      if($refId = $this->getData($field))
      {
        $object = Mage::getResourceSingleton($modelName)->getCachedObject($refId);
        if( ! $object) {
          $object = Mage::getModel($modelName)->isCacheEnabled(TRUE)->load($refId);
        }
      }

      // Otherwise use empty object
      else
      {
        $object = Mage::getModel($modelName);
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
      $modelName = $this->getResource()->getFieldModelName($field);
      $collection = Mage::getSingleton($modelName)->getCollection()->addFieldToFilter('_id', '$in', $this->getData($field));
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
      $this->_operations = new ArrayObject();
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
    return isset($this->_operations) ? $this->_operations->getArrayCopy() : array();
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
   * Initialize object original data
   * Overridden so that it resets the state of hasDataChanges when called ithout arguments
   *
   * @param string $key
   * @param mixed $data
   * @return Varien_Object
   */
  public function setOrigData($key=null, $data=null)
  {
    if (is_null($key)) {
      $this->_origData = $this->_data;
      $this->_hasDataChanges = FALSE;
    } else {
      $this->_origData[$key] = $data;
    }
    return $this;
  }

  /**
   * When memory usage is important, use this to ensure there are no memory leaks.
   *
   * @return Cm_Mongo_Model_Abstract
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
    return $this;
  }

  /**
   * Get the specified field as a Zend_Date or formatted if $format is given.
   *
   * @param string $field
   * @param string $format
   * @param boolean $useTimezone
   * @return Zend_Date|string
   */
  public function getAsZendDate($field, $format = null, $useTimezone = true)
  {
    $type = $this->getResource()->getFieldType($field);
    $data = $this->getData($field);
    if( ! $data) {
      return NULL;
    }

    switch($type)
    {
      case 'MongoDate':
        $date = Mage::app()->getLocale()->date($data->sec, null, null, $useTimezone);
        break;

      case 'datestring':
        $date = Mage::app()->getLocale()->date($data, Varien_Date::DATE_INTERNAL_FORMAT, null, false);
        break;

      case 'timestamp':
      case 'int':
      case 'float':
      default:
        $date = Mage::app()->getLocale()->date($data, null, null, $useTimezone);
        break;
    }
    if($format !== null) {
      return $date->toString($format);
    }
    return $date;
  }

  /**
   * Overridden to save the object in the cache if allowed
   *
   * @return Cm_Mongo_Model_Abstract
   */
  protected function _afterLoad()
  {
    if($this->isCacheEnabled()) {
      $this->getResource()->addObjectToCache($this);
    }
    return parent::_afterLoad();
  }

  /**
   * Overridden to save the object in the cache if allowed
   *
   * @return Cm_Mongo_Model_Abstract
   */
  protected function _afterDelete()
  {
    if($this->isCacheEnabled()) {
      $this->getResource()->removeObjectFromCache($this);
    }
    return parent::_afterDelete();
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

  /**
   * Enable/disable the cache or get the current state
   *
   * @param boolean $value
   * @return boolean|Cm_Mongo_Model_Abstract
   */
  public function isCacheEnabled($value = NULL)
  {
    if($value !== NULL) {
      $this->_isCacheEnabled = $value;
      return $this;
    }
    return $this->_isCacheEnabled;
  }
  
}