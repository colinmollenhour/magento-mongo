<?php

class Cm_Mongo_Model_Mongo_Job extends Cm_Mongo_Model_Resource_Abstract
{
  
  public function _construct()
  {
    $this->_init('mongo/job');
  }

  /**
   *
   * @return Cm_Mongo_Model_Job or FALSE if no job in ready queue
   */
  public function getNextJob()
  {
    $data = $this->_getWriteAdapter()->findAndModify($this->_collectionName, array(
        'query'  => array(
          'status'     => Cm_Mongo_Model_Job::STATUS_PENDING,
          'execute_at' => array('$lte' => time()),
        ),
        'sort'   => array(
          'priority'   => 1,
          'execute_at' => 1,
        ),
        'update' => array(
          'status'     => Cm_Mongo_Model_Job::STATUS_RUNNING,
          'pid'        => getmypid(),
        ),
        'fields' => $this->getDefaultLoadFields(new Varien_Object()),
        'new'    => true,
    ));

    if($data)
    {
      $job = Mage::getModel('t3/job');
      $this->hydrate($job, $data, TRUE);
      $this->_afterLoad($job);
      return $job;
    }
    
    return FALSE;
  }

  public function getDefaultLoadFields($object)
  {
    if($object->getLoadErrorLog()) {
      return array();
    } else {
      return array('error_log' => 0);
    }
  }

}
