<?php
/**
 * This class allows using PHPUnit fixtures with the EcomDev_PHPUnit module.
 */
class Cm_Mongo_Model_Fixture
  extends EcomDev_PHPUnit_Model_Fixture
  implements EcomDev_PHPUnit_Model_Fixture_Interface
{

  /** @var Cm_Mongo_Model_Mongo_Fixture */
  protected $_mongoResource;

  /**
   * Play nicely with MySQL fixtures, use separate resource.
   */
  protected function _construct()
  {
    parent::_construct();
    $this->_mongoResource = Mage::getResourceModel('mongo/fixture');
  }

  /**
   * @return \Cm_Mongo_Model_Mongo_Fixture
   */
  protected function getMongoResource()
  {
    return $this->_mongoResource;
  }

  /**
   * Applies collection data into test database
   *
   * @param array $collections
   * @return Cm_Mongo_Model_Fixture
   */
  protected function _applyCollections($collections)
  {
    if (!is_array($collections)) {
      throw new InvalidArgumentException(
        'Collections part should be an associative list with keys as collection entity and values as list of documents'
      );
    }

    foreach ($collections as $collectionEntity => $data) {
      $this->getMongoResource()->cleanCollection($collectionEntity);
      $this->_loadCollectionData($collectionEntity, $data);
    }
  }

  /**
   * Removes collection data from test data base
   *
   * @param array $collections
   * @return Cm_Mongo_Model_Fixture
   */
  protected function _discardCollections($collections)
  {
    if (!is_array($collections)) {
      throw new InvalidArgumentException(
        'Collections part should be an associative list with keys as collection entity and values as list of documents'
      );
    }

    foreach ($collections as $collectionEntity => $data) {
      $this->getMongoResource()->cleanCollection($collectionEntity);
    }
  }

  /**
   * @param string $collectionEntity
   * @param array $data
   */
  protected function _loadCollectionData($collectionEntity, $data)
  {
    if ( ! empty($data)) {
      $this->getMongoResource()->loadCollectionData($collectionEntity, $data);
    }
  }

}
