<?php
/**
 * Options:
 *  pluck
 *  glue
 */
class Cm_Mongo_Block_Adminhtml_Widget_Grid_Column_Renderer_Implode extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

  public function render(Varien_Object $row)
  {
    $value = (array) parent::_getValue($row);
    if($pluck = $this->getColumn()->getPluck()) {
      $array = array();
      foreach($value as $_value) {
        if(isset($_value[$pluck])) {
          $array[] = $_value[$pluck];
        }
      }
    } else {
      $array = $value;
    }
    $glue = $this->getColumn()->getGlue() ? $this->getColumn()->getGlue() : '<br/>';
    return implode($glue, $array);
  }

}
