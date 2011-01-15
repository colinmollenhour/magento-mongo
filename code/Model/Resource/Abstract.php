<?php

abstract class Cm_Mongo_Model_Resource_Abstract extends Mage_Core_Model_Resource_Abstract
{

  /** @var string  The resource group in the schema */
  protected $_resourceModel;

  /** @var string  The entity name in the schema */
  protected $_entityName;

  /** @var string  The collection name in the database */
  protected $_collectionName;

  /** @var Mongo_Collection */
  protected $_readCollection;

  /** @var Mongo_Collection */
  protected $_writeCollection;

  /**
   * Standard resource model initialization
   *
   * @param string $collection
   */
  protected function _init($resource)
  {
    $resource = explode('/', $resource, 2);
    $this->_resourceModel = $resource[0];
    $this->_entityName = $resource[1];
    $this->_collectionName = $this->getSchema()->getCollectionName($this->_resourceModel, $this->_entityName);
  }

  /**
   * Get the db read connection. Currently does not support alternate resources
   *
   * @return Mongo_Database
   */
  protected function _getReadAdapter()
  {
    return Mage::getSingleton('core/resource')->getConnection('mongo_read');
  }

  /**
   * Get the db read collection instance.
   * 
   * @return Mongo_Collection
   */
  protected function _getReadCollection()
  {
    if( ! $this->_readCollection) {
      $this->_readCollection = $this->_getReadAdapter()->selectCollection($this->_collectionName);
    }
    return $this->_readCollection;
  }

  /**
   * Get the db write connection. Currently does not support alternate resources
   *
   * @return Mongo_Database
   */
  protected function _getWriteAdapter()
  {
    return Mage::getSingleton('core/resource')->getConnection('mongo_write');
  }

  /**
   * Get the db write collection instance.
   *
   * @return Mongo_Collection
   */
  protected function _getWriteCollection()
  {
    if( ! $this->_writeCollection) {
      if($this->_getReadAdapter() == $this->_getWriteAdapter()) {
        $this->_writeCollection = $this->_getReadCollection();
      } else {
        $this->_writeCollection = $this->_getWriteAdapter()->selectCollection($this->_collectionName);
      }
    }
    return $this->_writeCollection;
  }

  /**
   * Make connection public.. Avoid usage
   *
   * @return Mongo_Database
   */
  public function getReadConnection()
  {
    return $this->_getReadAdapter();
  }

  /**
   * Make collection public.. Avoid usage
   *
   * @return Mongo_Collection
   */
  public function getReadCollection()
  {
    return $this->_getReadCollection();
  }

  /**
   * In Mongo, there is always an _id column
   *
   * @return string
   */
  public function getIdFieldName()
  {
    return '_id';
  }

  /**
   * Return the value as an id. Can be used to force id to be a specific type.
   * 
   * @param mixed $value
   * @return mixed
   */
  public function getIdValue($value)
  {
    return $value;
  }
  
  /**
   * Get cached reference to mongo schema model
   *
   * @return Cm_Mongo_Model_Schema
   */
  public function getSchema()
  {
    return Mage::getSingleton('mongo/schema');
  }

  /**
   * Get the schema config element for the given (or current) entity.
   *
   * @param string $entityName
   * @return Mage_Core_Model_Config_Element
   */
  public function getEntitySchema($entityName = NULL)
  {
    if($entityName === NULL) {
      return $this->getSchema()->getEntitySchema($this->_resourceModel, $this->_entityName);
    }
    if(strpos($entityName, '/') === FALSE) {
      return $this->getSchema()->getEntitySchema($this->_resourceModel, $entityName);
    }
    return $this->getSchema()->getEntitySchema($entityName);
  }

  /**
   * Get the actual collection name for the given entity
   *
   * Supports parameter formats:
   * 
   *   resourceModel/collection
   *   collection (resourceModel assumed from instance)
   * 
   * @param string $entityName
   * @return string
   */
  public function getCollectionName($entityName = NULL)
  {
    if($entityName === NULL) {
      return $this->_collectionName;
    }
    if (strpos($entityName, '/') === FALSE) {
      return $this->getSchema()->getCollectionName($this->_resourceModel, $entityName);
    }
    return $this->getSchema()->getCollectionName($entityName);
  }
  
  /**
   * Get the field mappings from the schema for this resource
   * 
   * @return Varien_Simplexml_Element
   */
  public function getFieldMappings()
  {
    return $this->getSchema()->getFieldMappings($this->_resourceModel, $this->_entityName);
  }

  /**
   * Get the field mapping data for the given field name.
   *
   * @param string $field
   * @return Varien_Simplexml_Element
   */
  public function getFieldMapping($field)
  {
    $mapping = $this->getFieldMappings()->{$field};
    if( ! $mapping) {
      throw new Exception("$this->_resourceModel/$this->_entityName does not have a field named $field.");
    }
    return $mapping;
  }

  /**
   * Get the field type
   *
   * @param string $field
   * @return string
   */
  public function getFieldType($field)
  {
    $mapping = $this->getFieldMapping($field);
    return isset($mapping->type) ? (string) $mapping->type : 'string';
  }

  /**
   * Get the embedded/referenced model name for the given field.
   *
   * @param string $field
   * @return string
   */
  public function getFieldModelName($field)
  {
    if( ! isset($this->_fieldModels[$field])) {
      $this->_fieldModels[$field] = (string) $this->getFieldMapping($field)->model;
    }
    return $this->_fieldModels[$field];
  }

  /**
   * Get an instance of an embedded/referenced model for the given field.
   * 
   * @param type $field
   * @return Cm_Mongo_Model_Abstract
   */
  public function getFieldModel($field)
  {
    return Mage::getModel($this->getFieldModelName($field));
  }

  /**
   * Get the resource instance of an embedded/referenced model for the given field.
   *
   * @param type $field
   * @return Cm_Mongo_Model_Resource_Abstract
   */
  public function getFieldResource($field)
  {
    return Mage::getResourceSingleton($this->getFieldModelName($field));
  }

  /**
   * Get the next auto-increment value for this resource entity
   *
   * @return int
   * @throws MongoException
   */
  public function getAutoIncrement()
  {
    return $this->_getWriteAdapter()->get_auto_increment($this->_resourceModel.'/'.$this->_entityName);
  }

  /**
   * Set created_at and/or update_at timestamps on the given object
   *
   * @param boolean $created
   * @param boolean $updated
   */
  public function setTimestamps(Mage_Core_Model_Abstract $object, $created = TRUE, $updated = TRUE)
  {
    if($created) {
      $object->setData('created_at', new MongoDate());
    }
    if($updated) {
      $object->setData('updated_at', new MongoDate());
    }
  }
  
  /**
   * Load an object
   *
   * @param   Mage_Core_Model_Abstract $object
   * @param   mixed $value
   * @param   string $field field to load by (defaults to model id)
   * @return  Mage_Core_Model_Mysql4_Abstract
   */
  public function load(Mage_Core_Model_Abstract $object, $value, $field=null)
  {
    if (is_null($field)) {
      $field = $this->getIdFieldName();
    }

    if ( ! is_null($value)) {
      $fields = $this->getDefaultLoadFields($object);
      $data = $this->getDocument($field, $value, $fields);
      if ($data) {
        $this->hydrate($object, $data, TRUE);
      } else {
        $object->isObjectNew(TRUE);
      }
    }

    $this->_afterLoad($object);

    return $this;
  }

  /**
   * Overload to choose only specific fields to be loaded.
   *
   * @param Varien_Object $object
   * @return array
   */
  public function getDefaultLoadFields($object)
  {
    return array(); // all fields
  }

  /**
   * Get the document for a load.
   *
   * @param string $field
   * @param mixed $value
   * @param array $fields
   * @return type 
   */
  public function getDocument($field, $value, $fields = array())
  {
    $value = $this->castToMongo($field, $value);
    return $this->_getReadCollection()->findOne(array($field => $value), $fields);
  }

  /**
   * Save object
   *
   * @param   Mage_Core_Model_Abstract $object
   * @return  Mage_Core_Model_Mysql4_Abstract
   */
  public function save(Mage_Core_Model_Abstract $object)
  {
    if ($object->isDeleted()) {
      return $this->delete($object);
    }

    $this->_beforeSave($object);

    // TRUE, do insert
    if($object->isObjectNew())
    {
      // Set created and updated timestamps
      $this->setTimestamps( $object,
        $this->getEntitySchema()->created_timestamp,
        $this->getEntitySchema()->updated_timestamp
      );

      // Collect data for mongo
      $data = $this->dehydrate($object);
      $ops = $object->getPendingOperations();

      // Set autoincrement
      if(empty($data['_id']) && $this->getEntitySchema()->autoincrement) {
        $data['_id'] = $this->getAutoIncrement();
      }

      // Translate $set operations to simple insert data if possible
      if(isset($ops['$set'])) {
        foreach($ops['$set'] as $key => $value) {
          if(strpos($key, '.') === false) {
            $data[$key] = $value;
            unset($ops['$set'][$key]);
          }
          // @TODO - expand . delimited keys
        }
        if( ! count($ops['$set'])) {
          unset($ops['$set']);
        }
      }

      // Insert document
      $this->_getWriteCollection()->insert($data, array('safe' => TRUE));
      if( ! $object->hasData('_id') || $data['_id'] != $object->getData('_id')) {
        $object->setData('_id', $data['_id']);
      }
      
      // Execute any pending operations
      if($ops) {
        $this->_getWriteCollection()->update(
          array($this->getIdFieldName() => $object->getId()),
          $ops,
          array('upsert' => FALSE, 'multiple' => FALSE, 'safe' => TRUE)
        );
      }
    }

    // FALSE, do update
    else if($object->isObjectNew() === FALSE)
    {
      if( ! $object->getId()) {
        throw new Mage_Core_Exception('Cannot save existing object without id.');
      }
      
      // Set updated timestamp only
      $this->setTimestamps( $object,
        FALSE,
        $this->getEntitySchema()->updated_timestamp
      );

      // Collect data for mongo and update using atomic operators
      $data = $this->dehydrate($object, TRUE);
      $data = $this->_flattenUpdateData($object->getOrigData(), $data);
      $ops = $object->getPendingOperations();
      if(isset($ops['$set'])) {
        $ops['$set'] = array_merge($data, $ops['$set']);
      }
      else if($data) {
        $ops['$set'] = $data;
      }
      if($ops) {
        $this->_getWriteCollection()->update(
          array($this->getIdFieldName() => $object->getId()),
          $ops,
          array('upsert' => FALSE, 'multiple' => FALSE, 'safe' => TRUE)
        );
      }
    }

    // Object status is not known, do upsert
    else
    {
      // Created timestamps not available on upsert
      $this->setTimestamps( $object,
        FALSE,
        $this->getEntitySchema()->updated_timestamp
      );

      // Collect data for mongo and operations and upsert
      $data = $this->dehydrate($object);
      $ops = $object->getPendingOperations();
      if(isset($ops['$set'])) {
        $ops['$set'] = array_merge($ops['$set'], $data);
      }
      else {
        $ops['$set'] = $data;
      }
      $this->_getWriteCollection()->update(
        array($this->getIdFieldName() => $object->getId()),
        $ops,
        array('upsert' => TRUE, 'multiple' => FALSE, 'safe' => TRUE)
      );
    }
    
    // Reset object state for successful save
    $object->setOrigData()->resetPendingOperations()->isObjectNew(FALSE);

    $this->_afterSave($object);

    return $this;
  }

  /**
   * Delete the object
   *
   * @param Varien_Object $object
   * @return Mage_Core_Model_Mysql4_Abstract
   */
  public function delete(Mage_Core_Model_Abstract $object)
  {
    $this->_beforeDelete($object);
    $this->_getWriteCollection()->remove(
      array($this->getIdFieldName() => $object->getId()),
      array('justOne' => TRUE, 'safe' => TRUE)
    );
    $this->_afterDelete($object);
    return $this;
  }

  /**
   * Load mongo data into a model using the schema mappings
   * 
   * @param Varien_Object $object
   * @param array $data
   * @param boolean $original   Is the data from the database?
   */
  public function hydrate(Varien_Object $object, $data, $original = FALSE)
  {
    $converter = $this->getMongoToPhpConverter();
    foreach($this->getFieldMappings() as $field => $mapping)
    {
      if( ! array_key_exists($field, $data)) {
        continue;
      }

      $value = $data[$field];
      if( $value !== NULL ) {
        $type = isset($mapping->type) ? (string) $mapping->type : 'string';
        if($type == 'embedded') {
          $model = $object->getDataUsingMethod($field);
          $model->getResource()->hydrate($model, $value, $original);
        }
        elseif($type == 'embeddedSet') {
          if( ! $object->getData($field)) {
            $set = new Varien_Data_Collection;
            foreach($value as $itemData)
            {
              $model = Mage::getModel((string)$mapping->model);
              $model->getResource()->hydrate($model, $itemData, $original);
              $set->addItem($model);
            }
            $object->setDataUsingMethod($field, $set);
          }
          else {
            $set = $object->getData($field);
            foreach($value as $index => $itemData)
            {
              $item = $set->getItemById($index);
              if( ! $item) {
                $item = Mage::getModel((string)$mapping->model);
              }
              $item->getResource()->hydrate($item, $itemData, $original);
            }
          }
        }
        else {
          $value = $converter->$type($mapping, $value);
          $object->setDataUsingMethod($field, $value);
        }
      }
      else {
        $object->setData($field, NULL);
      }
    }
    
    if($original) {
      $object->isObjectNew(FALSE);
      $object->setOrigData();
    }
  }
  
  /**
   * Extract data from model into Mongo-compatible array 
   * 
   * @param Varien_Object $object
   * @param boolean $forUpdate
   * @return array
   */
  public function dehydrate(Varien_Object $object, $forUpdate = FALSE)
  {
    $rawData = array();
    $converter = $this->getPhpToMongoConverter();
    foreach($this->getFieldMappings() as $field => $mapping)
    {
      if( ! $object->hasData($field) && ! isset($mapping->required)) {
        continue;
      }

      if($forUpdate && ! $object->dataHasChangedFor($field) && ! $object->getData($field) instanceof Varien_Object) {
        continue;
      }
      
      $rawValue = $object->getData($field);
      
      if($rawValue === NULL && isset($mapping->required) && ! isset($mapping->required->null)) {
        $rawValue = (string) $mapping->required;
      }

      $type = isset($mapping->type) ? (string) $mapping->type : 'string';
      
      // Embedded object
      if($type == 'embedded')
      {
        if($rawValue instanceof Cm_Mongo_Model_Abstract) {
          $value = $rawValue->getResource()->dehydrate($rawValue, $forUpdate);
        }
        else {
          $value = NULL;
        }
        if($forUpdate && empty($value)) {
          continue;
        }
      }
      
      // Sets of embedded objects
      else if($type == 'embeddedSet')
      {
        $value = array();
        if($rawValue instanceof Varien_Data_Collection) {
          $items = $rawValue->getItems();
        }
        else {
          $items = (array) $rawValue;
        }
        foreach($items as $item) {
          if($item instanceof Cm_Mongo_Model_Abstract) {
            $value[] = $item->getResource()->dehydrate($item, $forUpdate);
          }
          else if($item instanceof Varien_Object) {
            $value[] = $item->getData();
          }
          else {
            $value[] = (array) $item;
          }
        }
        if($forUpdate && empty($value)) {
          continue;
        }
      }
      
      // All other data types
      else {
        $value = $converter->$type($mapping, $object->getData($field));
      }
      
      $rawData[$field] = $value;
    }
    return $rawData;
  }

  /**
   * Get mongo to php data type converter instance
   *
   * @return Cm_Mongo_Model_Type_Tophp
   */
  public function getMongoToPhpConverter()
  {
    return Mage::getSingleton('mongo/type_tophp');
  }
  
  /**
   * Get php to mongo data type converter instance
   *
   * @return Cm_Mongo_Model_Type_Tomongo
   */
  public function getPhpToMongoConverter()
  {
    return Mage::getSingleton('mongo/type_tomongo');
  }

  /**
   * Cast a value to the proper type based on the schema's field mapping for this resource.
   *
   * @param string $field
   * @param mixed $value
   * @return mixed
   */
  public function castToMongo($field, $value)
  {
    $mapping = $this->getFieldMapping($field);
    $type = $this->getFieldType($field);
    return $this->getPhpToMongoConverter()->$type($mapping, $value);
  }

  /**
   * Flatten a nested array of values to update into . delimited keys
   * 
   * @param array $data
   * @param string $path   Used for recursion
   * @return array
   */
  public function _flattenUpdateData($orig, $data, $path = '')
  {
    $result = array();
    foreach($data as $key => $value)
    {
      if(is_array($value) && isset($orig[$key]))
      {
        $origData = $orig[$key];
        if($origData instanceof Cm_Mongo_Model_Abstract) {
          $origData = $origData->getOrigData();
        }
        $result2 = $this->_flattenUpdateData($origData, $value, $key.'.');
        foreach($result2 as $key2 => $value2) {
          $result[$path.$key2] = $value2;
        }
      }
      else
      {
        $result[$path.$key] = $value;
      }
    }
    return $result;
  }

  /* Optional callback placeholders */
  protected function _afterLoad(Mage_Core_Model_Abstract $object){}
  protected function _beforeSave(Mage_Core_Model_Abstract $object){}
  protected function _afterSave(Mage_Core_Model_Abstract $object){}
  protected function _beforeDelete(Mage_Core_Model_Abstract $object){}
  protected function _afterDelete(Mage_Core_Model_Abstract $object){}

  /* Overridden only to disable */
  public function beginTransaction(){}
  public function commit(){}
  public function rollback(){}


  /* * * * * * * * *
   * Object Caching
   */

  /**
   * Adds a collection to the resource's collection cache
   *
   * @param Cm_Mongo_Model_Resource_Collection_Abstract $collection
   */
  public function addCollectionToCache(Cm_Mongo_Model_Resource_Collection_Abstract $collection)
  {
    $hash = spl_object_hash($collection);
    $this->_caches[$hash] = $collection;
  }

  /**
   * Removes a collection from the resource's collection cache
   *
   * @param Cm_Mongo_Model_Resource_Collection_Abstract $collection
   */
  public function removeCollectionFromCache(Cm_Mongo_Model_Resource_Collection_Abstract $collection)
  {
    $hash = spl_object_hash($collection);
    unset($this->_caches[$hash]);
  }

  /**
   * Fetches an object from the resource's cache
   *
   * @param mixed $id
   * @return Cm_Mongo_Model_Abstract
   */
  public function getCachedObject($id)
  {
    foreach($this->_caches as $collection)
    {
      /* @var Cm_Mongo_Model_Resource_Collection_Abstract $collection */
      if($item = $collection->getItemById($id)) {
        return $item;
      }
    }
    return NULL;
  }

}
