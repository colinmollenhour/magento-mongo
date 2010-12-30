<?php

class Cm_Mongo_Helper_Data extends Mage_Core_Helper_Abstract
{
  
  /**
   * Get all mongo column renderers or just one.
   * 
   * @param string $key
   * @return mixed
   */
  public function getGridColumnRenderers($key = NULL)
  {
    $renderers = array(
      'datestring' => 'mongo/adminhtml_widget_grid_column_renderer_datestring',
    );
    if($key !== NULL) {
      return isset($renderers[$key]) ? $renderers[$key] : NULL;
    }
    return $renderers;
  }
  
  public function getProfilerHtml()
  {
    return Mage::app()->getLayout()->createBlock('mongo/profiler')->toHtml();
  }
  
}