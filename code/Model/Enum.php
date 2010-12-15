<?php

class Cm_Mongo_Model_Enum extends Cm_Mongo_Model_Abstract implements Mage_Eav_Model_Entity_Attribute_Source_Interface
{
  
  protected function _construct()
  {
    $this->_init('mongo/enum');
  }
  
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
    if( ! isset($values->$value)) {
      return FALSE;
    }
    return "$values->$value";
  }
  
  
  
}
