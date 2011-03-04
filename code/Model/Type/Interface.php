<?php
/**
 * Define custom data types which implement this interface
 */
interface Cm_Mongo_Model_Type_Interface
{
  
  public function toPHP($mapping, $value);
  
  public function toMongo($mapping, $value, $forUpdate = FALSE);
  
}
