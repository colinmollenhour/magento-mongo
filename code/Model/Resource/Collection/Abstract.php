<?php

class Cm_Mongo_Model_Resource_Collection_Abstract extends Varien_Data_Collection
{

  protected $_resourceModel;
  
  protected $_resource;
  
  protected $_conn;
  
  public function __construct($resource = NULL)
  {
    parent::__construct();
    $this->_construct();
    $this->_resource = $resource;
    $this->setConnection($this->getResource()->getReadConnection());
  }
  
  public function _construct()
  {
  }

  /**
   * Standard resource collection initalization
   *
   * @param string $model
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _init($model, $resourceModel=null)
  {
    $this->setItemObjectClass(Mage::getConfig()->getModelClassName($model));
    if (is_null($resourceModel)) {
      $resourceModel = $model;
    }
    $this->setResourceModel($resourceModel);
    return $this;
  }

  public function setResourceModel($model)
  {
    $this->_resourceModel = $model;
  }

  /**
  * Get resource instance
  *
  * @return Cm_Mongo_Model_Resource_Abstract
  */
  public function getResource()
  {
    if (empty($this->_resource)) {
      $this->_resource = Mage::getResourceModel($this->_resourceModel);
    }
    return $this->_resource;
  }

  public function getConnection()
  {
    return $this->_conn;
  }
  
  public function setConnection($conn)
  {
    $this->_conn = $conn;
    return $this;
  }
  
}