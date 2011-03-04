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
    $this->addColumn('name', array(
      'header'         => $this->__('Name'),
      'index'          => 'name',
    ));
    
    $this->addColumn('_id', array(
      'header'         => $this->__('ID'),
      'index'          => '_id',
    ));

    $this->addColumn('values_count', array(
      'header'         => $this->__('Values'),
      'getter'         => Cm_Mongo_Getter::factory('getValuesCount'),
      'width'          => '100px',
      'align'          => 'right',
      'sortable'       => false,
      'filter'         => false,
    ));

    parent::_prepareColumns();
  }
  
  public function getRowUrl($row)
  {
    return $this->getUrl('*/*/edit', array('id' => $row->getId()));
  }
  
}
