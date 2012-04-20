<?php
/**
 * Needed for proper handling of MongoIds as keys
 */
class Cm_Mongo_Collection extends Varien_Data_Collection
{

  /**
   * Adding item to item array. Overridden to ensure indexing on strings for safety with MongoId
   *
   * @param   Varien_Object $item
   * @return  Cm_Mongo_Collection
   */
  public function addItem(Varien_Object $item)
  {
    $itemId = $this->_getItemKey($item->getId());

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
   * @return  Cm_Mongo_Collection
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

}
