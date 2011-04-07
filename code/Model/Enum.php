<?php
/**
 * General purpose storage for a set of key => value pairs.
 */
class Cm_Mongo_Model_Enum
  extends Cm_Mongo_Model_Abstract
  implements Mage_Eav_Model_Entity_Attribute_Source_Interface
{
  
  protected function _construct()
  {
    $this->_init('mongo/enum');
  }

  /**
   * @param  string $key
   * @param  array $data
   * @return void
   */
  public function addDefaultValue($key, $data)
  {
    $defaults = $this->getDefaults();
    $defaults[$key] = $data;
    $this->setDefaults($defaults);
  }

  /**
   * @return array
   */
  public function getValues()
  {
    if($this->getStoreId()) {
      $storeCode = Mage::app()->getStore($this->getStoreId())->getCode();
      $stores = $this->getStores();
      if($stores && isset($stores[$storeCode])) {
        return (array) $stores[$storeCode];
      }
    }
    return (array) $this->getDefaults();
  }

  /**
   * @return int
   */
  public function getValuesCount()
  {
    return count($this->getValues());
  }

  /**
   * @param string $first
   * @return array
   */
  public function toOptionArray($first = NULL)
  {
    $options = $this->getAllOptions();
    if($first !== NULL) {
      array_unshift($options, array('value' => '', 'label' => $first));
    }
    return $options;
  }

  /**
   * @param string $first
   * @return array
   */
  public function toOptionHash($first = NULL)
  {
    $values = array();
    if($first !== NULL) {
      $first[''] = $first;
    }
    foreach($this->getValues() as $key => $data) {
      $values[$key] = $data['label'];
    }
    return $values;
  }

  /*
   * Implement methods for Mage_Eav_Model_Entity_Attribute_Source_Interface
   */

  /**
   * @return array
   */
  public function getAllOptions()
  {
    $values = array();
    foreach($this->getValues() as $key => $data) {
      $values[] = array('value' => $key, 'label' => $data['label']);
    }
    return $values;
  }

  /**
   * @param  string $value
   * @return bool|string
   */
  public function getOptionText($value) {
    $values = $this->getValues();
    if( ! isset($values[$value])) {
      return FALSE;
    }
    return $values[$value]['label'];
  }
  
}
