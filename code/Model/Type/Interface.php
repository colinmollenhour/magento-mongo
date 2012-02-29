<?php
/**
 * Define custom data types which implement this interface
 */
interface Cm_Mongo_Model_Type_Interface
{

  /**
   * @abstract
   * @param Mage_Core_Model_Config_Element $mapping
   * @param mixed $value
   */
  public function toPHP($mapping, $value);

  /**
   * @abstract
   * @param Mage_Core_Model_Config_Element $mapping
   * @param mixed $value
   * @param bool $forUpdate
   */
  public function toMongo($mapping, $value, $forUpdate = FALSE);
  
}
