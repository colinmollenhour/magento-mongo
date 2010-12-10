<?php

interface Cm_Mongo_Model_Type_Interface
{
  
  public function toPHP($value);
  
  public function toMongo($value);
  
}
