<?php
/**
 * Job model.
 *
 * Fields:
 *   _id
 *   task
 *   job_data
 *   status
 *   status_message
 *   pid
 *   retries
 *   priority
 *   execute_at
 *   error_log
 *   other_data
 */
class Cm_Mongo_Model_Job extends Cm_Mongo_Model_Abstract
{

  const STATUS_READY     = 'ready';
  const STATUS_RUNNING   = 'running';
  const STATUS_INVALID   = 'invalid';
  const STATUS_DISABLED  = 'disabled';
  const STATUS_SUCCESS   = 'success';
  const STATUS_FAILED    = 'failed';

  const LOGFILE          = 'mongo_queue_errors.txt';

  const DEFAULT_PRIORITY = 50;

  protected function _construct()
  {
    $this->_init('mongo/job');
  }

  /**
   * @return boolean
   */
  public function isTaskDisabled()
  {
    // Allow override
    if($this->getForceEnabled()) {
      return false;
    }
    // Check specific task config
    if($this->getTaskConfig()->is('disabled')) {
      return true;
    }
    // Check group config if a group is specified
    if($groups = (string) $this->getTaskConfig('groups')) {
      foreach(explode(',',$groups) as $group) {
        if(Mage::app()->getConfig()->getNode('mongo_queue/groups/'.$group)->is('disabled')) {
          return true;
        }
      }
    }
    return false;
  }

  /**
   * @param string $node
   * @return Mage_Core_Model_Config_Element
   */
  public function getTaskConfig($node = '')
  {
    $node = 'mongo_queue/tasks/'.$this->getTask().($node ? "/$node" : '');
    return Mage::app()->getConfig()->getNode($node);
  }

  /**
   * @param string $status
   * @param string|NULL $statusMessage
   * @return Cm_Mongo_Model_Job
   */
  public function updateStatus($status, $statusMessage = NULL)
  {
    $this->setStatus($status)
         ->setStatusMessage($statusMessage)
         ->setPid(NULL);
    return $this;
  }

  /**
   * @return boolean
   */
  public function canRetry()
  {
    $maxRetries = (int) $this->getTaskConfig('retries');
    return $maxRetries && $maxRetries > $this->getRetries();
  }

  /**
   * @return Cm_Mongo_Model_Job
   */
  public function scheduleRetry()
  {
    $time = time();
    $schedule = (string) $this->getTaskConfig('retry_schedule');
    if($schedule) {
      $schedule = preg_split('/\s*,\s*/', $schedule, null, PREG_SPLIT_NO_EMPTY);
      $retries = (int) $this->getRetries();
      $modifier = isset($schedule[$retries]) ? $schedule[$retries] : end($schedule);
      $time = strtotime($modifier, $time);
    }
    $this->updateStatus(self::STATUS_READY)
         ->op('$inc', 'retries', 1)
         ->setExecuteAt($time);
    return $this;
  }

  /**
   * @param string|Exception $e
   * @return Cm_Mongo_Model_Job
   */
  public function logError($e)
  {
    if($this->getTaskConfig()->is('logging'))
    {
      $logfile = (string) $this->getTaskConfig('logfile');
      if( ! $logfile) {
        $logfile = self::LOGFILE;
      }
      $message = "PID:{$this->getPid()}, Retries:{$this->getRetries()}, ".
                 "Task:{$this->getTask()}, Data:".json_encode($this->getJobData())."\n".
                 "Error: $e";
      Mage::log($message, Zend_Log::DEBUG, $logfile);
    }
    $this->op('$push', 'error_log', array('time' => new MongoDate(), 'message' => "$e"));
    return $this;
  }

  /**
   * Executes the task and updates the status.
   *
   * @throws Exception on an unexpected configuration error.
   */
  public function run()
  {
    if($this->getStatus() != self::STATUS_RUNNING) {
      throw new Exception('Cannot run job when status is not \'running\'.');
    }

    // Mark invalid if task not defined
    if( ! $this->getTaskConfig('group')) {
      $this->updateStatus(self::STATUS_INVALID, 'Task not properly defined.')
           ->save();
      return;
    }

    // If task is globally disabled, put job on hold.
    if($this->isTaskDisabled()) {
      $this->updateStatus(self::STATUS_DISABLED)
           ->save();
      return;
    }

    // Load task
    switch((string) $this->getTaskConfig('type')) {
      case 'helper':
        $object = Mage::helper((string)$this->getTaskConfig('class'));
        break;
      case 'model':
        $object = Mage::getModel((string)$this->getTaskConfig('class'));
        break;
      case 'resource':
        $object = Mage::getResourceSingleton((string)$this->getTaskConfig('class'));
        break;
      case 'singleton':
      default:
        $object = Mage::getSingleton((string)$this->getTaskConfig('class'));
        break;
    }
    if( ! $object) {
      $this->updateStatus(self::STATUS_INVALID, 'Could not get task model.')
           ->save();
      return;
    }

    // Confirm existence of method
    $method = (string) $this->getTaskConfig('method');
    if( ! method_exists($object, $method)) {
      $this->updateStatus(self::STATUS_INVALID, 'Cannot call task method.')
           ->save();
      return;
    }

    $data = new Varien_Object($this->getJobData());

    // Run task
    try {

      // Load object if a load_index is specified
      if($this->getTaskConfig('type') == 'model' && ($loadIndex = $this->getTaskConfig('load_index'))) {
        if( ! $data->hasData($loadIndex)) {
          throw new Exception('No id in job data to load by.');
        }
        $object->load($data->getData($loadIndex));
        $data->unsetData($loadIndex);
        if( ! $object->getId()) {
          throw new Exception('Object could not be loaded.');
        }
      }

      $data->setJob($this);
      $object->$method($data);
      
      // If status not changed by task, assume success
      if($this->getStatus() == self::STATUS_RUNNING || $this->getStatus() == self::STATUS_SUCCESS) {
        // Update or delete?
        if($this->getTaskConfig()->is('after_success','keep')) {
          $this->updateStatus(self::STATUS_SUCCESS);
        }
        // Delete by default
        else {
          $this->isDeleted(true);
        }
      }
    }

    // If exception caught, retry if possible
    catch(Exception $e) {
      if($this->getStatus() == self::STATUS_RUNNING) {
        $this->logError($e);

        // Retry
        if($this->canRetry()) {
          $this->scheduleRetry();
        }
        // Delete if specified
        else if($this->getTaskConfig()->is('after_failure','delete')) {
          $this->isDeleted(true);
        }
        // Keep by default
        else {
          $this->updateStatus(self::STATUS_FAILED, $e->getMessage());
        }
      }
    }
    $this->save();
  }

  /**
   * Method for running via Magento cron
   */
  public function runCron()
  {
    if( ! Mage::getStoreConfigFlag('system/mongo_queue/enabled')) {
      return;
    }
    $limit = (int) Mage::getStoreConfig('system/mongo_queue/limit');
    $time  = (int) Mage::getStoreConfig('system/mongo_queue/time');
    Mage::helper('mongo/queue')->runQueue($limit, $time);
  }

  protected function _beforeSave()
  {
    if($this->getData('priority') === null) {
      $priority = $this->getTaskConfig('priority');
      if($priority === false) {
        $priority = Cm_Mongo_Model_Job::DEFAULT_PRIORITY;
      }
      $this->setData('priority', (int) $priority);
    }
  }
}

