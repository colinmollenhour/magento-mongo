<?php

class Cm_Mongo_Block_Adminhtml_Enum_Edit_Values_Value extends Mage_Core_Block_Abstract
{

  protected function _toHtml()
  {
    $inputName = $this->getInputName();
    $columnName = $this->getColumnName();
    $column = $this->getColumn();

    return '<input type="text" name="' . $inputName . '" value="#{' . $columnName . '}" ' .
        ($column['size'] ? 'size="' . $column['size'] . '"' : '') . ' class="' .
        (isset($column['class']) ? $column['class'] : 'input-text') . '"'.
        (isset($column['style']) ? ' style="'.$column['style'] . '"' : '') .
        (isset($column['disabled']) ? ' rel="disabled"' : '') . '/>';
  }

}