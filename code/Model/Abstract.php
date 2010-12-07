<?php

abstract class Cm_Mongo_Model_Abstract extends Mage_Core_Model_Abstract {

  /** @var boolean */
  protected $_isNewObject = NULL;

  public function isNewObject($flag = -1)
  {
    if($flag !== -1) {
      $this->_isNewObject = $flag;
    }
    return $this->_isNewObject;
  }

  public function setData($key, $value)
  {
    // Load all data
    if(is_array($key)) {
      $this->_data = $key;
    }

    // Set one value
    else {
      $this->_data[$key] = $value;
    }
    return $this;
  }

  public function getData($key='', $index=null)
  {
    // Get all data
    if(''===$key) {
      if($this->isDirty() && isset($this->_data['_id'])) {
        $this->load($this->getId());
      }
      return $this->_data;
    }

    // Get one value
    return parent::getData($key, $index);
  }

  public function getPendingOperations()
  {
    return $this->_operations;
  }

  public function resetPendingOperations()
  {
    $this->_operations = array();
  }

}