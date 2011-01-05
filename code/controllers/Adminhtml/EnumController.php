<?php

class Cm_Mongo_Adminhtml_EnumController extends Mage_Adminhtml_Controller_Action {
  
  public function indexAction()
  {
    $this->loadLayout()
      ->_setActiveMenu('system/enums')
      ->_title($this->__('Enumerations'))
      ->_addContent($this->getLayout()->createBlock('mongo/adminhtml_enum'))
      ->renderLayout();
  }
  
}