<?php

abstract class Cm_Mongo_Model_Resource_Document extends Mage_Core_Model_Resource_Abstract {

  /** @var string  The resource group in the schema */
  protected $_resourceModel;

  /** @var string  The collection name in the schema */
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
    $this->_collectionName = $resource[1];
    $this->_schema = Mage::getSingleton('mongo/schema');
  }

  /**
   * Get the db read connection. Currently does not support alternate resources
   *
   * @return Mongo_Collection
   */
  protected function _getReadAdapter()
  {
    return Mage::getSingleton('core/resource')->getConnection('mongo_read');
  }

  /**
   * Get the db write connection. Currently does not support alternate resources
   *
   * @return Mongo_Collection
   */
  protected function _getWriteAdapter()
  {
    return Mage::getSingleton('core/resource')->getConnection('mongo_write');
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
  public function getCollectionName($entityName)
  {
    if (strpos($entityName, '/') == FALSE) {
     $entityName = $this->_resourceModel.'/'.$entityName;
    }

    return $this->_schema->getCollectionName($entityName);
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
        $object->setData($data)->setOrigData()->unsetData();
        $this->hydrate($object, $data);
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

    $data = $object->getPendingOperations();

    // upsert handles preset ids for new data and updates on old data
    if ($object->getId()) {
      $this->_getWriteAdapter()->selectCollection($this->_collectionName)->update(
        array($this->getIdFieldName() => $object->getId()),
        $data,
        array('upsert' => TRUE, 'multiple' => FALSE, 'safe' => TRUE)
      );
    }

    // plain insert when no id is present
    else {
      $insertData = $data['$set'];
      //@TODO expand keys on .
      unset($data['$set']);

      // Insert regular data
      $this->_getWriteAdapter()->selectCollection($this->_collectionName)->insert($insertData, array('safe' => TRUE));
      $object->setData(array('_id' => $insertData['_id']), TRUE);
      
      // Update additional data
      if($data) {
        $this->_getWriteAdapter()->selectCollection($this->_collectionName)->update(
          array($this->getIdFieldName() => $object->getId()),
          $data,
          array('upsert' => FALSE, 'multiple' => FALSE, 'safe' => TRUE)
        );
      }

    }
    $object->setOrigData();
    $object->flagDirty(NULL,FALSE);
    $object->resetPendingOperations();

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

  public function hydrate(Mage_Core_Model_Abstract $object, $data)
  {
    foreach($this->_getFieldMappings() as $field => $mapping)
    {
      $key = ($field == 'id' ? '_id' : isset($mapping->name) ? $mapping->name : $field);
      $rawValue = isset($data[$key]) ? $data[$key] : NULL;
      if($rawValue === NULL)
      {
        continue;
      }

      $object->setData($field, $this->convertMongoToPHP($mapping, $rawValue));
    }
  }

  public function convertMongoToPHP($mapping, $value)
  {
    switch($mapping->type)
    {
      case 'id':
        return $value;
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
        return (in_array($value,$mapping->options) ? $value : NULL);
      case 'embedded':
        return Mage::getModel($mapping->model)->setData($value);
      case 'embeddedSet':
        $set = new Varien_Data_Collection();
        $set->setItemObjectClass($mapping->model);
        foreach($value as $itemData)
        {
          $set->addItem(Mage::getModel($mapping->model)->setData($itemData));
        }
        return $set;
      case 'reference':
        return Mage::getModel($mapping->model)->load($value);
      case 'referenceSet':
        return Mage::getModel($mapping->model)->getCollection()->loadAll($value);
    }

    return Mage::getSingleton($mapping->type)->toPHP($mapping, $value);
  }

}