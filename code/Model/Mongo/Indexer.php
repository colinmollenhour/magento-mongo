<?php
/**
 * Indexer Resource Model
 *
 * @see Cm_Mongo_Model_Indexer
 */
class Cm_Mongo_Model_Mongo_Indexer extends Cm_Mongo_Model_Resource_Abstract
{
  const COLLECTION_STEP = 100;

  const FILTER_TYPE_INTEGER = 'integer';
  const FILTER_TYPE_BOOLEAN = 'boolean';
  const FILTER_TYPE_STRING  = 'string';
  const FILTER_TYPE_ARRAY   = 'array';

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
      $collection = $this->getCollection($indexer);
      $size = $collection->getSize();
      $page = 1;
      do {
        $collection->getQuery()
          ->skip(($page - 1)*self::COLLECTION_STEP)
          ->limit(self::COLLECTION_STEP)
          ->sort(array('_id' => Mongo_Collection::ASC));
        foreach ($collection as $model /** @var $model Cm_Mongo_Model_Abstract */) {
          $this->updateIndex($indexer, $model);
        }
        $collection->reset();
        $page++;
      } while ($page <= intval(ceil($size/self::COLLECTION_STEP)));
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
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function getCollection(stdClass $indexer)
  {
    /** @var $collection Cm_Mongo_Model_Resource_Collection_Abstract */
    $collection = Mage::getModel($indexer->entity)->getCollection();
    // Add filters
    if (isset($indexer->filters)) {
      $this->_addFilters($collection, $indexer->filters);
    }
    // Add fields to selection
    if (isset($indexer->fields)) {
      $collection->addFieldToResults(array_values($indexer->fields));
    }
    // Add fields to preload
    if (isset($indexer->dependency)) {
      $collection->addFieldToPreload(array_values($indexer->dependency));
    }

    return $collection;
  }

  /**
   * @param Cm_Mongo_Model_Resource_Collection_Abstract $collection
   * @param array $filters
   */
  protected function _addFilters(Cm_Mongo_Model_Resource_Collection_Abstract $collection, array $filters)
  {
    foreach ($filters as $filter) {
      $field = $filter['field'];
      $filterType = isset($filter['type']) ? $filter['type'] : self::FILTER_TYPE_STRING;
      switch ($filterType) {
        case self::FILTER_TYPE_BOOLEAN :
          $value = (bool) $filter['value'];
          break;
        case self::FILTER_TYPE_INTEGER :
          $value = intval($filter['value']);
          break;
        case self::FILTER_TYPE_ARRAY :
          $value = explode(',', $filter['value']);
          break;
        case self::FILTER_TYPE_STRING :
        default:
          $value = strval($filter['value']);
          break;
      }
      $condition = isset($filter['condition']) ? $filter['condition'] : 'eq';
      // Add filter to collection
      $collection->addFieldToFilter($field, $condition, $value);
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