<?php
/**
 * Indexer Resource Model
 *
 * @see Cm_Mongo_Model_Indexer
 */
class Cm_Mongo_Model_Mongo_Indexer extends Cm_Mongo_Model_Resource_Abstract
{
  public function _construct()
  {
    $this->_init('mongo/indexer');
  }

  /**
   * Reindex all data
   */
  public function reindexAll()
  {
    foreach (Mage::getModel('mongo/indexer')->getAllIndexers() as $indexer) {
      $this->removeIndex($indexer);
      /** @var $collection Cm_Mongo_Model_Resource_Collection_Abstract */
      $collection = Mage::getModel($indexer->entity)->getCollection();
      foreach ($collection as $model /** @var $model Cm_Mongo_Model_Abstract */) {
        $this->updateIndex($indexer, $model);
      }
    }
  }

  /**
   * @param stdClass $indexer
   * @param Mage_Core_Model_Abstract $model
   */
  public function updateIndex(stdClass $indexer, Mage_Core_Model_Abstract $model)
  {
    if ($resource = $this->_getTargetResource($indexer)) {
      $resource->update(
        array($indexer->reference => $model->getId()),
        '$set',
        '_.'.$indexer->index,
        $this->_getValue($indexer, $model),
        array('multiple' => TRUE)
      );
    }
  }

  /**
   * Remove index for the specified or all entities.
   *
   * @param stdClass $indexer
   * @param array|int $id
   */
  public function removeIndex(stdClass $indexer, $id = null)
  {
    $criteria = array();
    if (is_int($id)) {
      $criteria = array($indexer->reference => $id);
    } elseif (is_array($id)) {
      $criteria = array($indexer->reference => array('$in' => $id));
    }
    if ($resource = $this->_getTargetResource($indexer)) {
      $resource->update(
        $criteria,
        '$unset',
        array('_.'.$indexer->index => 1),
        NULL,
        array('multiple' => TRUE)
      );
    }
  }

  /**
   * @param stdClass $indexer
   * @return Cm_Mongo_Model_Resource_Abstract|bool
   * @throws Exception
   */
  protected function _getTargetResource(stdClass $indexer)
  {
    $resource = Mage::getResourceSingleton($indexer->resource);
    if (!$resource instanceof Cm_Mongo_Model_Resource_Abstract) {
      if (Mage::getIsDeveloperMode()) {
        throw new Exception(sprintf('Unknown resource model with the name: %s.', $indexer->resource));
      }
      return FALSE;
    }
    return $resource;
  }

  /**
   * Get field value
   *
   * @param stdClass $indexer
   * @param Mage_Core_Model_Abstract $model
   * @return mixed
   */
  protected function _getValue(stdClass $indexer, Mage_Core_Model_Abstract $model)
  {
    $value = (!empty($indexer->method) && is_callable(array($model, $indexer->method)))
      ? $model->{$indexer->method}()
      : $model->getData($indexer->changedFieldName);
    return $value;
  }
}