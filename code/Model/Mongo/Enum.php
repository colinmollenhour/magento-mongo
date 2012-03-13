<?php
/**
 * Enum Resource Model
 */
class Cm_Mongo_Model_Mongo_Enum extends Cm_Mongo_Model_Resource_Abstract
{
  
  public function _construct()
  {
    $this->_init('mongo/enum');
  }

  public function getDefaultLoadFields($object)
  {
    $fields = array(
      'name' => 1,
      'defaults' => 1
    );
    if($object->getStoreId()) {
      $fields['stores.'.Mage::app()->getStore($object->getStoreId())->getCode()] = 1;
    }
    return $fields;
  }

}
