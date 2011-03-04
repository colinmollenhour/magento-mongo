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

  public function newAction()
  {
    $this->_forward('edit');
  }

  public function editAction()
  {
    $id = $this->getRequest()->getParam('id');
    $model = Mage::getModel('mongo/enum');
    Mage::register('edit_model', $model);

    if( $id ){
      $model->load($id);
      if( ! $model->getId()) {
        $this->_getSession()->addError($this->__('This enumeration no longer exists.'));
        $this->_redirect('*/*/');
        return;
      }
    }

    $data = $this->_getSession()->getFormData(true);
    if( ! empty($data)) {
      $model->loadData($data);
    }

    $this->loadLayout()
      ->_setActiveMenu('system/enums')
      ->_title($this->__('Enumerations'))
      ->_title($model->getId() ? $this->__('Edit %s', $model->getName()) : $this->__('New Enumeration'))
      ->_addContent($this->getLayout()->createBlock('mongo/adminhtml_enum_edit'))
      ->renderLayout();
  }

  public function saveAction()
  {
    if($data = $this->getRequest()->getPost()) {
      $id = $data['_id'];
      $model = Mage::getModel('mongo/enum');
      /* @var Cm_Mongo_Model_Enum $model */

      if( ! $id ) {
        $this->_getSession()->addError($this->__('Enumeration identifier not specified.'));
        $this->_getSession()->setFormData(FALSE);
        $this->_redirect('*/*/');
        return;
      }

      $model->load($id);

      try {

        $defaults = array();
        unset($data['defaults']['__empty']);
        foreach($data['defaults'] as $key => $value) {
          $index = $value['value'];
          if( ! strlen($index)) {
            continue;
          }
          unset($value['value']);
          $defaults[$index] = $value;
        }
        $data['defaults'] = $defaults;

        // Load data
        $model->setData($data);

        $model->save();

        $this->_getSession()->addSuccess($this->__('%s has been saved.', $model->getName()));
        $this->_getSession()->setFormData(FALSE);
        if($this->getRequest()->getParam('back')) {
          $this->_redirect('*/*/edit', array('id' => $model->getId()));
          return;
        }
      }
      catch(Exception $e) {
        $this->_getSession()->addError($e->getMessage());
        $this->_getSession()->setFormData(FALSE);
        $this->_redirect('*/*/edit', array('id' => $id));
        return;
      }
    }
    // back to grid by default
    $this->_redirect('*/*/');
  }

  public function deleteAction()
  {
    $id = $this->getRequest()->getParam('id');
    $model = Mage::getModel('mongo/enum');

    $model->load($id);
    if( ! $model->getId()) {
      $this->_getSession()->addError($this->__('This enumeration no longer exists.'));
      $this->_redirect('*/*/');
      return;
    }

    $name = $model->getName();
    $model->delete();

    $this->_getSession()->addSuccess($this->__('%s has been deleted.', $name));
    $this->_getSession()->setFormData(FALSE);
    $this->_redirect('*/*/');
  }

}