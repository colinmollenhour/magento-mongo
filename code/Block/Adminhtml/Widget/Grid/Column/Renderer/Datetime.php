<?php
/**
 * Adminhtml grid item renderer for MongoDate compatible with Magento datetime
 */
class Cm_Mongo_Block_Adminhtml_Widget_Grid_Column_Renderer_Datetime extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Datetime
{

  /**
   * Renders grid column
   *
   * @param   Varien_Object $row
   * @return  string
   */
  public function render(Varien_Object $row)
  {
    if ($data = $this->_getValue($row))
    {
      try {
        if($data instanceof MongoDate) {
          $date = new Zend_Date($data->sec, null, Mage::app()->getLocale()->getLocale());
        }
        else if($data instanceof Zend_Date) {
          $date = $data;
          $date->setLocale(Mage::app()->getLocale()->getLocale());
        }
        else if(is_int($data) || is_float($data) || ctype_digit($data)) {
          $date = new Zend_Date($data, null, Mage::app()->getLocale()->getLocale());
        }
        else {
          $date = Mage::app()->getLocale()->date($data, Varien_Date::DATETIME_INTERNAL_FORMAT);
        }
        if ($timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE)) {
          $date->setTimezone($timezone);
        }
        $data = $date->toString($this->_getFormat());
      }
      catch (Exception $e)
      {
        $data = $this->getColumn()->getDefault();
      }
      return $data;
    }
    return $this->getColumn()->getDefault();
  }
  
}
