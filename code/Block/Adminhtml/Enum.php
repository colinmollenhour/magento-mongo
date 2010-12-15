<?php

class Cm_Mongo_Block_Adminhtml_Enum extends Mage_Adminhtml_Block_Widget_Grid_Container
{
  
  public function __construct()
  {
    $this->_blockGroup = 'mongo';
    $this->_controller = 'adminhtml_enum';
    $this->_headerText = $this->__('Manage Enumerations');
    parent::__construct();
  }
  
}
