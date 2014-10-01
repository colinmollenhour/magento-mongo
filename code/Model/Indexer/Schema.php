<?php
/**
 * Mongo indexer schema registry (singleton)
 */
class Cm_Mongo_Model_Indexer_Schema extends Varien_Simplexml_Config
{
  const CACHE_KEY     = 'mongo_indexer_schema';
  const CACHE_TAG     = 'mongo_indexer_schema';

  /**
   * Load cache on instantiation
   *
   * @param null|string|Varien_Simplexml_Element $sourceData
   */
  public function __construct($sourceData=null)
  {
    $this->setCacheId(self::CACHE_KEY);
    $this->setCacheTags(array(self::CACHE_TAG));
    parent::__construct($sourceData);

    if (Mage::app()->useCache(self::CACHE_KEY)) {
      $this->setCache(Mage::app()->getCache());
      if ($this->loadCache()) {
        return;
      }
    }

    $config = Mage::getConfig()->loadModulesConfiguration('mongo_indexer.xml');
    $this->setXml($config->getNode());

    if (Mage::app()->useCache(self::CACHE_KEY)) {
      $this->saveCache();
    }
  }

  /**
   * Get indexers for the entity
   *
   * @param string $entity
   * @return array
   */
  public function getEntityIndexers($entity)
  {
    $indexers = array();
    if ($this->getNode('indexer')) {
      foreach ($this->getNode('indexer')->{$entity} as $node) {
        $indexers = array_merge($indexers, $this->_getEntityTypeNodeIndexers($node));
      }
    }
    return $indexers;
  }

  /**
   * Get all indexers
   *
   * @return array
   */
  public function getAllIndexers()
  {
    $indexers = array();
    if ($this->getNode('indexer')) {
      foreach ($this->getNode('indexer')->children() as $key => $node) {
        $indexers = array_merge($indexers, $this->_getEntityTypeNodeIndexers($node));
      }
    }
    return $indexers;
  }

  /**
   * Get entity type indexers
   *
   * @param Mage_Core_Model_Config_Element $node
   * @return array
   */
  protected function _getEntityTypeNodeIndexers(Mage_Core_Model_Config_Element $node)
  {
    $indexers = array();
    foreach ($node as $fieldName => $fieldNode) {
      foreach ($fieldNode as $indexerName => $indexerNode) {
        $indexer = (object) $indexerNode->asCanonicalArray();
        $indexer->fieldName = $fieldName;
        $indexer->indexerName = $indexerName;
        if (!isset($indexer->ignore)) {
          $indexers[] = $indexer;
        }
      }
    }
    return $indexers;
  }
}
