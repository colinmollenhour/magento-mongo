<?php

class Cm_Mongo_Block_Adminhtml_Enum_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
  
  public function __construct()
  {
    $this->_controller = 'adminhtml_enum';
    $this->_blockGroup = 'mongo';

    parent::__construct();
    
    $this->_updateButton('save', 'label', $this->__('Save Enumeration'));
    $this->_updateButton('delete', 'label', $this->__('Delete Enumeration'));

    $this->setData('form_action_url', $this->getUrl('*/*/save'));
  }
  
  public function getHeaderText()
  {
    $model = Mage::registry('edit_model');
    if($model->getId()) {
      return $this->__('Edit %s', $model->getName());
    } else {
      return $this->__('New Enumeration');
    }
  }
  
}
