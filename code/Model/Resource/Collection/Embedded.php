<?php
/**
 * This class should always be used when populating embeddedSet fields
 */
class Cm_Mongo_Model_Resource_Collection_Embedded extends Varien_Data_Collection
{

  /** @var Cm_Mongo_Model_Abstract */
  protected $_root;

  /** @var string */
  protected $_path;

  /**
   * Store the root object and path so that items added can have proper root and path
   *
   * @param Cm_Mongo_Model_Abstract $object
   * @param string $path
   */
  public function _setEmbeddedIn($object, $path)
  {
    $this->_root = $object;
    $this->_path = $path;
  }

  /**
   * Override addItem to not attempt to use item id as key, and to set item root and path.
   * Also to ensure indexing on strings for safety with MongoId
   *
   * @param Varien_Object $item
   * @return Cm_Mongo_Model_Resource_Collection_Embedded
   */
  public function addItem(Varien_Object $item)
  {
    $index = count($this->_items);
    $item->_setEmbeddedIn($this->_root, $this->_path.'.'.$index);
    $itemId = $item->getIdFieldName() ? $this->_getItemKey($item->getId()) : NULL;

    if ($itemId !== NULL) {
      if (isset($this->_items[$itemId])) {
        throw new Exception('Item ('.get_class($item).') with the same id "'.$item->getId().'" already exist');
      }
      $this->_items[$itemId] = $item;
    } else {
      $this->_items[] = $item;
    }
    return $this;
  }

  /**
   * Remove item from collection by item key
   *
   * @param   mixed $key
   * @return  Varien_Data_Collection
   */
  public function removeItemByKey($key)
  {
    $key = $this->_getItemKey($key);
    if (isset($this->_items[$key])) {
      unset($this->_items[$key]);
    }
    return $this;
  }

  /**
   * Get a key for an item
   *
   * @param mixed $id
   * @return mixed
   */
  protected function _getItemKey($id)
  {
    if($id instanceof MongoId) {
      return (string) $id;
    }
    return $id;
  }

  /**
   * Checks to see if any of the items have data changes.
   * Provides interface consistent with Cm_Mongo_Model_Abstract
   *
   * @return boolean
   */
  public function hasDataChanges()
  {
    foreach($this->_items as $item) {
      if($item->hasDataChanges()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Call the reset method of each item and clear the collection.
   * Provides interface consistent with Cm_Mongo_Model_Abstract
   *
   * @return Cm_Mongo_Model_Resource_Collection_Embedded
   */
  public function reset()
  {
    foreach ($this->_items as $item) {
      $item->reset();
    }
    $this->clear();
    $this->_root = $this->_path = NULL;
    return $this;
  }

}

