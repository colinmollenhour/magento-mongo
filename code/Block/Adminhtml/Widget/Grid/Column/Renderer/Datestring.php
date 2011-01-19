<?php

/**
 * Adminhtml grid item renderer for mongo type "datestring"
 */

class Cm_Mongo_Block_Adminhtml_Widget_Grid_Column_Renderer_Datestring extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
  protected $_defaultWidth = 160;
  /**
   * Date format string
   */
  protected static $_format = null;

  /**
   * Retrieve date format
   *
   * @return string
   */
  protected function _getFormat()
  {
    $format = $this->getColumn()->getFormat();
    if (!$format) {
      if (is_null(self::$_format)) {
        try {
          self::$_format = Mage::app()->getLocale()->getDateFormat(
            Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM
          );
        }
        catch (Exception $e) {

        }
      }
      $format = self::$_format;
    }
    return $format;
  }

  /**
   * Renders grid column
   *
   * @param   Varien_Object $row
   * @return  string
   */
  public function render(Varien_Object $row)
  {
    if ($data = $this->_getValue($row)) {
      $format = $this->_getFormat();
      $data = Mage::app()->getLocale()->date($data, Varien_Date::DATE_INTERNAL_FORMAT, NULL, FALSE)->toString($format);
      return $data;
    }
    return $this->getColumn()->getDefault();
  }
  
}
