<?php

abstract class Cm_Mongo_Model_Resource_Abstract extends Mage_Core_Model_Resource_Abstract {

  /** @var string  The resource group in the schema */
  protected $_resourceModel;

  /** @var string  The entity name in the schema */
  protected $_entityName;

  /** @var string  The collection name in the database */
  protected $_collectionName;

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
   * Get the db write connection. Currently does not support alternate resources
   *
   * @return Mongo_Database
   */
  protected function _getWriteAdapter()
  {
    return Mage::getSingleton('core/resource')->getConnection('mongo_write');
  }

  /**
   * Temporary resolving collection compatibility
   *
   * @return Mongo_Database
   */
  public function getReadConnection()
  {
    return $this->_getReadAdapter();
  }

  /**
   * No function
   */
  public function beginTransaction()
  {}
  
  /**
   * No function
   */
  public function commit()
  {}
  
  /**
   * No function
   */
  public function rollback()
  {}
  
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
   * Get an instance of an embedded model for the given field.
   * 
   * @param type $field
   * @return Cm_Mongo_Model_Abstract
   */
  public function getFieldModel($field)
  {
    $model = (string) $this->getFieldMapping($field)->model;
    $object = Mage::getModel($model);
    return $object;
  }

  /**
   * Get the field mapping data for the given field name.
   *
   * @param string $field
   * @return string
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
      $data = $this->_getDocument($field, $value);
      if ($data) {
        $this->hydrate($object, $data);
        $object->setOrigData();
      } else {
        $object->isNewObject(TRUE);
      }
    }

    $this->_afterLoad($object);

    return $this;
  }
  
  /**
   * Get the document for a load.
   * 
   * @param string $field
   * @param mixed $value
   * @param boolean $cast  Cast the value to the mongo type?
   * @return type 
   */
  protected function _getDocument($field, $value, $cast = TRUE)
  {
    $value = $this->castToMongo($field, $value);
    return $this->_getReadAdapter()->selectCollection($this->_collectionName)->findOne(array($field => $value));
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

    if( ! $object->isNewObject() && ! $object->getId()) {
      throw new Mage_Core_Exception('Cannot save existing object without id.');
    }
    
    // TRUE, do insert
    if($object->isNewObject())
    {
      // Set created and updated timestamps
      $this->setTimestamps( $object,
        $this->getEntitySchema()->created_timestamp,
        $this->getEntitySchema()->updated_timestamp
      );

      // Collect data for mongo and insert
      $data = $this->dehydrate($object);
      if(empty($data['_id']) && $this->getEntitySchema()->autoincrement) {
        $data['_id'] = $this->getAutoIncrement();
      }
      $this->_getWriteAdapter()->selectCollection($this->_collectionName)->insert($data, array('safe' => TRUE));
      $object->setData('_id', $data['_id']);
      
      // Update additional data
      $ops = $object->getPendingOperations();
      if($ops) {
        $this->_getWriteAdapter()->selectCollection($this->_collectionName)->update(
          array($this->getIdFieldName() => $object->getId()),
          $ops,
          array('upsert' => FALSE, 'multiple' => FALSE, 'safe' => TRUE)
        );
      }
    }

    // FALSE, do update
    else if($object->isNewObject() === FALSE)
    {
      // Set updated timestamp only
      $this->setTimestamps( $object,
        FALSE,
        $this->getEntitySchema()->updated_timestamp
      );

      // Collect data for mongo and update using atomic operators
      $data = $this->dehydrate($object, TRUE);
      $ops = $object->getPendingOperations();
      if(isset($ops['$set'])) {
        $ops['$set'] = array_merge($ops['$set'], $data);
      }
      else {
        $ops['$set'] = $data;
      }
      if($ops) {
        $this->_getWriteAdapter()->selectCollection($this->_collectionName)->update(
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
      $this->_getWriteAdapter()->selectCollection($this->_collectionName)->update(
        array($this->getIdFieldName() => $object->getId()),
        $ops,
        array('upsert' => TRUE, 'multiple' => FALSE, 'safe' => TRUE)
      );
    }
    
    // Reset object state for successful save
    $object->setOrigData()->resetPendingOperations()->isNewObject(FALSE);

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
    $this->_getWriteAdapter()->selectCollection($this->_collectionName)->remove(
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
   */
  public function hydrate(Varien_Object $object, $data)
  {
    $idFieldName = $this->getIdFieldName();
    $converter = $this->getMongoToPhpConverter();
    foreach($this->getFieldMappings() as $field => $mapping)
    {
      if( ! array_key_exists($field, $data)) {
        continue;
      }

      if( $data[$field] !== NULL ) {
        $type = isset($mapping->type) ? (string) $mapping->type : 'string';
        $value = $converter->$type($mapping, $data[$field]);
        $object->setDataUsingMethod($field, $value);
      }
      else {
        $object->setData($field, NULL);
      }
    }
    $object->isNewObject(FALSE);
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
    $changedOnly = $forUpdate && $object->isNewObject();
    $converter = $this->getPhpToMongoConverter();
    foreach($this->getFieldMappings() as $field => $mapping)
    {
      if( ! $object->hasData($field) && ! isset($mapping->required)) {
        continue;
      }

      if($changedOnly && ! $object->hasDataChangedFor($field)) {
        continue;
      }
      
      $rawValue = $object->getData($field);
      if($rawValue === NULL && isset($mapping->required) && ! isset($mapping->required->null)) {
        $rawValue = $mapping->required;
      }

      $type = isset($mapping->type) ? (string) $mapping->type : 'string';
      $value = $converter->$type($mapping, $object->getData($field), $forUpdate);
      
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
    return $this->getPhpToMongoConverter()->{"$mapping->type"}($mapping, $value);
  }

  protected function _afterLoad(Mage_Core_Model_Abstract $object){}
  protected function _beforeSave(Mage_Core_Model_Abstract $object){}
  protected function _afterSave(Mage_Core_Model_Abstract $object){}
  protected function _beforeDelete(Mage_Core_Model_Abstract $object){}
  protected function _afterDelete(Mage_Core_Model_Abstract $object){}

}
