<?php

class Cm_Mongo_Block_Adminhtml_Enum_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
  
  public function __construct()
  {
    parent::__construct();
    $this->setId('mongoEnums');
    //$this->setDefaultSort('_id');
  }
  
  public function _prepareCollection()
  {
    $this->setCollection(Mage::getResourceModel('mongo/enum_collection'));
    parent::_prepareCollection();
  }
  
  public function _prepareColumns()
  {
    $this->addColumn('_id', array(
      'header'         => $this->__('ID'),
      'width'          => '100px',
      'index'          => '_id',
    ));
    
    $this->addColumn('label', array(
      'header'         => $this->__('Name'),
      'width'          => '100px',
      'index'          => 'label',
    ));
    
    $this->addColumn('aciton', array(
      'header'         => $this->__('Actions'),
      'width'          => '100px',
      'type'           => 'action',
      'actions'        => array(array(
          'url'		   => array('base' => '*/*/edit'),
          'caption'	   => $this->__('Edit'),
          'field'      => 'id',
          'index'      => 'id',
      ))
    ));
    
    parent::_prepareColumns();
  }
  
  public function getRowUrl($row)
  {
    return $this->getUrl('*/*/edit', array('id' => $row->getId()));
  }
  
}
