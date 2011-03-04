<?php

class Cm_Mongo_Block_Adminhtml_Enum_Edit_Values extends Mage_Adminhtml_Block_System_Config_Form_Field_Array_Abstract
{

  protected function _prepareToRender()
  {
    $this->_addButtonLabel = $this->__('Add Value');
    
    $valueRenderer = Mage::app()->getLayout()->createBlock('mongo/adminhtml_enum_edit_values_value');
    $valueColumn = $this->addColumn('value', array(
        'label'    => $this->__('Identifier'),
        'style'    => 'width:120px;',
        'renderer' => $valueRenderer,
    ));
    if($this->getDisableExisting()) {
      $this->_columns['value']['disabled'] = true;
    }
    
    $this->addColumn('label', array(
        'label'    => $this->__('Label'),
        'style'    => 'width:120px;',
    ));
  }

  protected function _toHtml()
  {
    $html = parent::_toHtml();
    $html .= '<script type="text/javascript">$$(\'input[rel=disabled]\').each(function(el){ el.readOnly = true; });</script>';
    return $html;
  }

}
