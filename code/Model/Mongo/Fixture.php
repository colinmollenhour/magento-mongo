<?php
/**
 * Use for loading fixtures with EcomDev_PHPUnit
 */
class Cm_Mongo_Model_Mongo_Fixture extends Cm_Mongo_Model_Resource_Abstract
{

  protected $_collections = array();

  public function _construct()
  {
    // No _init!
  }

  /**
   * @param $collectionEntity
   * @return Mongo_Collection
   */
  protected function _getCollection($collectionEntity)
  {
    if(strpos($collectionEntity, '/')) {
      $collectionEntity = $this->getCollectionName($collectionEntity);
    }
    if( ! isset($this->_collections[$collectionEntity])) {
      $this->_collections[$collectionEntity] = $this->_getWriteAdapter()->selectCollection($collectionEntity);
    }
    return $this->_collections[$collectionEntity];
  }

  /**
   * @param string $collection
   */
  public function cleanCollection($collection)
  {
    $this->_getCollection($collection)->remove_safe(array());
  }

  /**
   * @param string $collection
   * @param array $data
   */
  public function loadCollectionData($collection, $data)
  {
    $model = Mage::getModel($collection);
    foreach ($data as $index => $document) {
      $model->setData($document);
      $data[$index] = $model->getResource()->dehydrate($model);
    }
    $this->_getCollection($collection)->batchInsert($data);
  }

}
