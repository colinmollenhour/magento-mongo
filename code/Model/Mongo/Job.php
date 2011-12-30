<?php
/**
 * @see Cm_Mongo_Model_Job
 */
class Cm_Mongo_Model_Mongo_Job extends Cm_Mongo_Model_Resource_Abstract
{
  
  public function _construct()
  {
    $this->_init('mongo/job');
  }

  /**
   * @return Cm_Mongo_Model_Job|bool   FALSE if no job ready in queue
   */
  public function getNextJob()
  {
    $result = $this->_getWriteAdapter()->command_safe(array(
        'findAndModify' => $this->_collectionName,
        'query'  => array(
          'status'     => Cm_Mongo_Model_Job::STATUS_READY,
          'execute_at' => array('$lte' => new MongoDate),
        ),
        'sort'   => array(
          'priority'   => 1,
          'execute_at' => 1,
        ),
        'update' => array(
          '$set' => array(
            'status'     => Cm_Mongo_Model_Job::STATUS_RUNNING,
            'pid'        => getmypid(),
          ),
        ),
        'fields' => $this->getDefaultLoadFields(new Varien_Object),
        'new'    => true,
    ));
    if( empty($result['value'])) {
      return FALSE;
    }

    $data = $result['value'];
    $job = Mage::getModel('mongo/job');
    $this->hydrate($job, $data, TRUE);
    return $job;
  }

  /**
   * Attempts to reload a job using findAndModify so as to safely reserve the job
   * to be run by the current process
   *
   * @param Cm_Mongo_Model_Job $job
   * @return boolean
   */
  public function reloadJobForRunning(Cm_Mongo_Model_Job $job)
  {
    $result = $this->_getWriteAdapter()->command(array(
        'findAndModify' => $this->_collectionName,
        'query'  => array(
          '_id'        => $job->getId(),
          'status'     => Cm_Mongo_Model_Job::STATUS_READY,
        ),
        'update' => array(
          '$set' => array(
            'status'     => Cm_Mongo_Model_Job::STATUS_RUNNING,
            'pid'        => getmypid(),
          ),
        ),
        'fields' => $this->getDefaultLoadFields(new Varien_Object()),
        'new'    => true,
    ));

    if( empty($result['ok'])) {
      return FALSE;
    }

    $data = $result['value'];
    $this->hydrate($job, $data, TRUE);
    return TRUE;
  }

  /**
   * @param array $criteria
   * @param array $update
   * @return bool
   */
  public function pauseJobs($criteria, $update = array())
  {
    $criteria['status'] = Cm_Mongo_Model_Job::STATUS_READY;
    $criteria['$atomic'] = TRUE;
    $update['status'] = Cm_Mongo_Model_Job::STATUS_DISABLED;
    return $this->update($criteria, $update, array('multiple' => TRUE));
  }

  /**
   * @param array $criteria
   * @param array $update
   * @return bool
   */
  public function resumeJobs($criteria, $update = array())
  {
    $criteria['status'] = Cm_Mongo_Model_Job::STATUS_DISABLED;
    $update['status'] = Cm_Mongo_Model_Job::STATUS_READY;
    return $this->update($criteria, $update, array('multiple' => TRUE));
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
