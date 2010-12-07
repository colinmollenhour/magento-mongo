<?php
/**
 * Mongo schema registry (singleton)
 */
class Cm_Mongo_Model_Schema extends Varien_Simplexml_Config
{
  const CACHE_KEY     = 'mongo_schema';
  const CACHE_TAG     = 'mongo_schema';

  /**
   * Load cache on instantiation
   */
  public function __construct($sourceData=null)
  {
    $this->setCacheId(self::CACHE_KEY);
    $this->setCacheTags(array(self::CACHE_TAG));
    parent::__construct($sourceData);

    if (Mage::app()->useCache(self::CACHE_KEY)) {
      if ($this->_loadCache()) {
        return $this;
      }
    }

    $config = Mage::getConfig()->loadModulesConfiguration('mongo.xml');
    $this->setXml($config->getNode());

    if (Mage::app()->useCache(self::CACHE_KEY)) {
      $this->_saveCache();
    }
  }

  /**
   * Get the collection name for a resource entity.
   * 
   * @param string $resource
   * @param string $entity
   * @return string
   */
  public function getCollectionName($resource, $entity = NULL)
  {
    return (string) $this->getEntitySchema($resource, $entity)->collection;
  }

  /**
   * Get the schema config node for a resource entity.
   * 
   * @param string $resource
   * @param string $entity
   * @return Varien_Simplexml_Element
   */
  public function getEntitySchema($resource, $entity = NULL)
  {
    if(is_null($entity)) {
      list($resource, $entity) = explode('/',$resource);
    }
    return $this->getNode()->{$resource}->{$entity};
  }

  /**
   * Get the field mappings for a resource entity.
   * 
   * @param string $resource
   * @param string $entity
   * @return string
   */
  public function getFieldMappings($resource, $entity = NULL)
  {
    return (string) $this->getEntitySchema($resource, $entity)->fields;
  }

  /**
   * Get the schema config node for a resource.
   * 
   * @param string $resource
   * @return Varien_Simplexml_Element
   */
  public function getResourceSchema($resource)
  {
    return $this->getNode()->{$resource};
  }

  /**
   * Retrieve cache object
   *
   * @return Zend_Cache_Frontend_File
   */
  public function getCache()
  {
    return Mage::app()->getCache();
  }

}
