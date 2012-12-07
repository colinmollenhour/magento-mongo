<?php
/**
 *
 * @method Cm_Mongo_Model_Mongo_Indexer _getResource()
 */
class Cm_Mongo_Model_Indexer extends Mage_Index_Model_Indexer_Abstract
{
  const ACTION_TYPE_REMOVE = 'remove';
  const ACTION_TYPE_UPDATE = 'update';

  const INDEXER_DUPLICATES = 'duplicates';

  /**
   * Initialize resource
   */
  protected function _construct()
  {
    $this->_init('mongo/indexer');
  }

  /**
   * Get Indexer name
   *
   * @return string
   */
  public function getName()
  {
    return Mage::helper('mongo')->__('General Indexer');
  }

  /**
   * Get Indexer description
   *
   * @return string
   */
  public function getDescription()
  {
    return Mage::helper('mongo')->__('Index entities to avoid references call.');
  }

  /**
   * @return Cm_Mongo_Model_Indexer_Schema
   */
  public function getSchema()
  {
    return Mage::getSingleton('mongo/indexer_schema');
  }

  /**
   * Register indexer required data inside event object
   *
   * @param Mage_Index_Model_Event $event
   * @return void
   */
  protected function _registerEvent(Mage_Index_Model_Event $event)
  {
    $event->addNewData('indexers', $this->getEntityIndexers($event));
  }

  /**
   * Process event based on event state data
   *
   * @param Mage_Index_Model_Event $event
   */
  protected function _processEvent(Mage_Index_Model_Event $event)
  {
    $data = $event->getNewData('indexers');
    $object = $event->getDataObject(); /** @var $object Mage_Core_Model_Abstract */

    foreach ($data['indexers'] as $indexer) {
      try {
        switch ($event->getType()) {
          case Mage_Index_Model_Event::TYPE_SAVE :
            $this->_getResource()->updateIndex($indexer, $object);
            break;
          case Mage_Index_Model_Event::TYPE_DELETE :
            $this->_getResource()->removeIndex($indexer, $object->getId());
            break;
          case Mage_Index_Model_Event::TYPE_MASS_ACTION :
          case Mage_Index_Model_Event::TYPE_REINDEX :
            $indexerIds = (array) $object->getIndexerIds();
            if (!empty($indexerIds)) {
              $actionType = ($object->getActionType() == self::ACTION_TYPE_REMOVE)
                ? self::ACTION_TYPE_REMOVE
                : self::ACTION_TYPE_UPDATE;
              switch ($actionType) {
                case self::ACTION_TYPE_UPDATE :
                  $collection = $object->getCollection(); /** @var $collection Cm_Mongo_Model_Resource_Collection_Abstract */
                  $collection->addFieldToFilter('_id', 'in', $indexerIds);
                  foreach ($collection as $item) {
                    $this->_getResource()->updateIndex($indexer, $item);
                  }
                  break;
                case self::ACTION_TYPE_REMOVE :
                  $this->_getResource()->removeIndex($indexer, $indexerIds);
                  break;
              }
            }
            break;
        }
      } catch (Exception $e) {
        Mage::logException($e);
      }
    }
  }

  /**
   * Check if indexer matched specific entity and action type
   *
   * @param   string $entity
   * @param   string $type
   * @return  bool
   */
  public function matchEntityAndType($entity, $type)
  {
    return (bool) count($this->getSchema()->getEntityIndexers($entity));
  }

  /**
   * Get indexers for the entity
   *
   * @param Mage_Index_Model_Event $event
   * @return array
   */
  public function getEntityIndexers(Mage_Index_Model_Event $event)
  {
    $indexers = array();
    $allIndexers = array();
    $object = $event->getDataObject(); /** @var $object Mage_Core_Model_Abstract */
    $duplicates = array();
    foreach ($this->getSchema()->getEntityIndexers($event->getEntity()) as $indexer) {
      if (!$this->_validate($indexer)) {
        continue;
      }
      if (!isset($indexer->duplicates)) {
        $allIndexers[$indexer->fieldName.'/'.$indexer->indexerName] = $indexer;
      }
      if ($object->dataHasChangedFor($indexer->fieldName) || $object->isDeleted()) {
        if (isset($indexer->duplicates)) {
          $duplicates[$indexer->duplicates] = $indexer->method;
        } else {
          $indexers[$indexer->fieldName.'/'.$indexer->indexerName] = $indexer;
        }
      }
    }
    foreach ($duplicates as $key => $method) {
      if (isset($allIndexers[$key])) {
        $indexers[$key] = $allIndexers[$key];
        $indexers[$key]->method = $method;
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
    $allIndexers = array();
    $duplicates = array();
    foreach ($this->getSchema()->getAllIndexers() as $indexer) {
      if (!$this->_validate($indexer)) {
        continue;
      }
      if (!isset($indexer->duplicates)) {
        $key = $indexer->fieldName.'/'.$indexer->indexerName;
        $allIndexers[$key] = $indexers[$key] = $indexer;
      } else {
        $duplicates[$indexer->duplicates] = $indexer->method;
      }
    }
    foreach ($duplicates as $key => $method) {
      if (isset($allIndexers[$key])) {
        $indexers[$key] = $allIndexers[$key];
        $indexers[$key]->method = $method;
      }
    }
    return $indexers;
  }

  /**
   * Validate indexer configuration
   *
   * @param Mage_Core_Model_Config_Element $indexer
   * @return bool
   * @throws Exception
   */
  protected function _validate(stdClass $indexer)
  {
    try {

      if (!isset($indexer->indexerName)) {
        throw new Exception('Indexer name is required.');
      }
      if (!isset($indexer->fieldName)) {
        throw new Exception(sprintf('Changed field name is required. Indexer name: %s.', $indexer->indexerName));
      }

      if (isset($indexer->duplicates)) {
        if (!isset($indexer->method)) {
          throw new Exception(sprintf('Duplicates indexer must specify method. Indexer name: %s.', $indexer->indexerName));
        }
      } else {
        if (!isset($indexer->entity)) {
          throw new Exception('Source entity model is required.');
        }
        if (!isset($indexer->resource)) {
          throw new Exception(sprintf('Resource model to update index for is required. Indexer name: %s.', $indexer->indexerName));
        }
        if (!isset($indexer->reference)) {
          throw new Exception(sprintf('Reference field is required. Indexer name: %s.', $indexer->indexerName));
        }
        if (!isset($indexer->index)) {
          throw new Exception(sprintf('Location to save the indexed data is required. Indexer name: %s.', $indexer->indexerName));
        }
      }

      return TRUE;

    } catch (Exception $e) {
      Mage::logException($e);
      Mage::log(json_encode($indexer));
      if (Mage::getIsDeveloperMode()) {
        throw $e;
      }
    }

    return FALSE;
  }
}