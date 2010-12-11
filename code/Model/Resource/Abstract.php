<?php

abstract class Cm_Mongo_Model_Resource_Abstract extends Mage_Core_Model_Resource_Abstract {

  /** @var string  The resource group in the schema */
  protected $_resourceModel;

  /** @var string  The entity name in the schema */
  protected $_entityName;

  /** @var string  The collection name in the database */
  protected $_collectionName;

  /** @var Cm_Mongo_Model_Schema */
  protected $_schema;
  
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
    $this->_schema = Mage::getSingleton('mongo/schema');
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
    return $this->_schema;
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
    
    if (strpos($entityName, '/') == FALSE) {
     $entityName = $this->_resourceModel.'/'.$entityName;
    }

    return $this->_schema->getCollectionName($entityName);
  }
  
  /**
   * Get the field mappings from the schema for this resource
   * 
   * @return Varien_Simplexml_Element
   */
  public function getFieldMappings()
  {
    return $this->_schema->getFieldMappings($this->_resourceModel, $this->_entityName);
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
      $data = $this->_getReadAdapter()->selectCollection($this->_collectionName)->findOne(array($field => $value));
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
   * Save object object data
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
    if($object->isNewObject()) {
      // Insert regular data
      $data = $this->dehydrate($object);
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
    else if($object->isNewObject() === FALSE) {
      // Insert regular data
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
    else {
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
    foreach($this->getFieldMappings() as $field => $mapping)
    {
      $key = ($field == 'id' ? '_id' : $field);
      if(array_key_exists($key, $data)) {
        $rawValue = $data[$key];
      }
      else {
        continue;
      }

      if($rawValue !== NULL || $mapping->notnull) {
        $value = $this->convertMongoToPHP($mapping, $rawValue);
        $object->setDataUsingMethod($field, $value);
      }
      else {
        $object->setData($field, NULL);
      }
    }
    $object->unflagDirty()->isNewObject(FALSE);
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
    foreach($this->getFieldMappings() as $field => $mapping)
    {
      if( ! $object->hasData($field)) {
        continue;
      }
      if($changedOnly && ! $object->hasDataChangedFor($field)) {
        continue;
      }
      $value = $this->convertPHPToMongo($mapping, $object->getData($field), $forUpdate);
      
      $key = ($field == 'id' ? '_id' : $field);
      $rawData[$key] = $value;
    }
    return $rawData;
  }

  public function convertMongoToPHP($mapping, $value)
  {
    switch($mapping->type)
    {
      case 'id':
        return $this->getIdValue($value);
      case 'int':
        return (int) $value;
      case 'string':
        return (string) $value;
      case 'float':
        return (float) $value;
      case 'bool':
        return (bool) $value;
      case 'collection':
        return array_values((array) $value);
      case 'hash':
        return (object) $value;
      // @TODO - bin data types
      case 'enum':
        return isset($mapping->options->$value) ? $value : NULL;
      case 'embedded':
        $model = Mage::getModel($mapping->model);
        $model->getResource()->hydrate($model, $value);
        return $model;
      case 'embeddedSet':
        $set = new Varien_Data_Collection();
        //$set->setItemObjectClass($mapping->model);
        foreach($value as $itemData)
        {
          $model = Mage::getModel($mapping->model);
          $model->getResource()->hydrate($model, $itemData);
          $model->setOrigData();
          $set->addItem($model);
        }
        return $set;
      case 'reference':
        return $value; //Mage::getModel($mapping->model)->load($value);
      case 'referenceSet':
        return $value; //Mage::getModel($mapping->model)->getCollection()->loadAll($value);
    }

    return Mage::getSingleton($mapping->type)->toPHP($mapping, $value);
  }

  public function convertPHPToMongo($mapping, $value, $forUpdate = FALSE)
  {
    switch($mapping->type)
    {
      case 'id':
        return $this->getIdValue($value);
      case 'int':
        return (int) $value;
      case 'string':
        return (string) $value;
      case 'float':
        return (float) $value;
      case 'bool':
        return (bool) $value;
      case 'collection':
        return array_values((array) $value);
      case 'hash':
        return (object) $value;
      // @TODO - bin data types
      case 'enum':
        return isset($mapping->options->$value) ? $value : NULL;
      case 'embedded':
        if( ! is_object($value)) {
          return NULL;
        }
        $data = $value->getResource()->dehydrate($value, $forUpdate);
        return $data;
      case 'embeddedSet':
        $data = array();
        if($value instanceof Varien_Data_Collection) {
          $items = $value->getItems();
        }
        else {
          $items = (array) $value;
        }
        foreach($items as $item) {
          if($item instanceof Cm_Mongo_Model_Abstract) {
            $data[] = $item->getResource()->dehydrate($item);
          }
          else if($item instanceof Varien_Object) {
            $data[] = $item->getData();
          }
          else {
            $data[] = (array) $item;
          }
        }
        return $data;
      case 'reference':
        return is_object($value) ? $value->getId() : $value;
      case 'referenceSet':
        $ids = array();
        foreach($value as $item) {
          $ids[] = is_object($item) ? $item->getId() : $item;
        }
        return $ids;
    }

    return Mage::getSingleton($mapping->type)->toMongo($mapping, $value);
  }
  
}