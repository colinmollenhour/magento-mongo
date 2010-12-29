<?php

class Cm_Mongo_Block_Profiler extends Mage_Core_Block_Abstract
{

  protected function _toHtml()
  {
    if (!$this->_beforeToHtml()
      || !Mage::getStoreConfigFlag('dev/debug/mongo_profiler')
      || !Mage::helper('core')->isDevAllowed()) {
      return '';
    }

    $timers = Cm_Mongo_Profiler::getTimers();

    ob_start();
    echo "<a href=\"javascript:void(0)\" onclick=\"$('profiler_section').style.display=$('profiler_section').style.display==''?'none':''\">[mongo profiler]</a>";
    echo '<div id="mongo_profiler_section" style="background:white; display:block">';
    echo '<table border="1" cellspacing="0" cellpadding="2" style="width:auto">';
    echo '<tr><th>Mongo Profiler</th><th>Time</th><th>Cnt</th></tr>';
    foreach ($timers as $name=>$timer) {
      $sum = Cm_Mongo_Profiler::fetch($name,'sum');
      $count = Cm_Mongo_Profiler::fetch($name,'count');
      echo '<tr>'
          .'<td align="left">'.$name.'</td>'
          .'<td>'.number_format($sum,4).'</td>'
          .'<td align="right">'.$count.'</td>'
          .'</tr>'
      ;
    }
    echo '</table>';
    echo '</div>';

    return ob_get_clean();
  }

}