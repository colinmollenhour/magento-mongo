<?php
/**
 * Job Collection
 */
class Cm_Mongo_Model_Mongo_Job_Collection extends Cm_Mongo_Model_Resource_Collection_Abstract
{
  
  public function _construct()
  {
    $this->_init('mongo/job');
  }
  
}
