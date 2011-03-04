<?php

class Cm_Mongo_Block_Adminhtml_Enum_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
  
  protected function _prepareForm()
  {
    $form = new Varien_Data_Form(array(
        'id' => 'edit_form',
        'action' => $this->getData('action'),
        'method' => 'post',
        'use_container' => TRUE
    ));

    $fieldset = $form->addFieldset('main_fieldset', array(
        'legend'   => $this->__('Enumeration Info'),
    ));

    $model = Mage::registry('edit_model');

    $idField = $fieldset->addField('_id', 'text', array(
        'name'     => '_id',
        'label'    => $this->__('Identifier'),
        'required' => TRUE,
    ));
  
    if($model->getId()) {
      $idField->setReadonly(TRUE);
    }

    $fieldset->addField('name', 'text', array(
        'name'     => 'name',
        'label'    => $this->__('Name'),
        'required' => TRUE,
    ));

    $field = $fieldset->addField('defaults', 'text', array(
        'name'     => 'defaults',
        'label'    => $this->__('Default Values'),
    ));

    $defaultsRenderer = $this->getLayout()->createBlock('mongo/adminhtml_enum_edit_values', 'values');
    $defaultsRenderer->setDisableExisting(!!$model->getId());
    $field->setRenderer($defaultsRenderer);

    $formData = $model->getData();

    //var_dump($formData); die;
    
    $defaults = array();
    if( ! empty($formData['defaults'])) {
      foreach($formData['defaults'] as $key => $value) {
        $value['value'] = $key;
        $defaults[$key] = $value;
      }
    }
    $formData['defaults'] = $defaults;

    $form->setValues($formData);

    $this->setForm($form);
    return parent::_prepareForm();
  }
  
}
