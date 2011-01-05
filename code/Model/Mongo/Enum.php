<?php

class Cm_Mongo_Model_Mongo_Enum extends Cm_Mongo_Model_Resource_Abstract
{
  
  public function _construct()
  {
    $this->_init('mongo/enum');
  }
  
  protected function _getDocument(Cm_Mongo_Model_Abstract $object, $field, $value)
  {
    $fields = array('name' => 1, 'defaults' => 1);
    if($object->getStoreId()) {
      $fields['stores.'.Mage::app()->getStore($object->getStoreId())->getCode()] = 1;
    }
    return $this->_getReadAdapter()->selectCollection($this->_collectionName)
       ->findOne(array($field => $value), $fields);
  }
  
}