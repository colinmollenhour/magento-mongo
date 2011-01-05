<?php

class Cm_Mongo_Model_Enum
  extends Cm_Mongo_Model_Abstract
  implements Mage_Eav_Model_Entity_Attribute_Source_Interface
{
  
  protected function _construct()
  {
    $this->_init('mongo/enum');
  }

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

  public function toOptionArray($first = NULL)
  {
    $options = $this->getAllOptions();
    if($first !== NULL) {
      array_unshift($options, array('value' => '', 'label' => $first));
    }
    return $options;
  }

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

  public function getAllOptions()
  {
    $values = array();
    foreach($this->getValues() as $key => $data) {
      $values[] = array('value' => $key, 'label' => $data['label']);
    }
    return $values;
  }
  
  public function getOptionText($value) {
    $values = $this->getValues();
    if( ! isset($values[$value])) {
      return FALSE;
    }
    return $values[$value];
  }
  
}
