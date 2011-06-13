<?php
/**
 * Job model. Jobs are run with a variety of options as defined in config.xml as shown below.
 *
 * Remember that these options can be overridden in local.xml, for example to temporarily disable a group of tasks
 * or to adjust the priorities of a task.
 *
 * <config>
 *   <mongo_queue>
 *     <tasks>
 *       <your_task_name>
 *         <type>singleton</type>           <!-- What type of class should be used? helper|model|resource|singleton  (default: singleton) -->
 *         <class>foo/bar</class>           <!-- Path to load class by, according to <type> -->
 *         <method>runQueue</method>        <!-- Method to run. Signature: (Varien_Object $data, Cm_Mongo_Model_Job $job) -->
 *         <load_index>_id</load_index>     <!-- If type is 'model' the model will be loaded with the data at this index before the method is called. -->
 *         <retries>3</retries>             <!-- Set the maximum number of retries for this task -->
 *         <retry_schedule>                 <!-- If no retry schedule is given retries are scheduled immediately -->
 *           +3 min, +1 hour, +12 hours     <!-- The schedule is a list of intervals which are strtotime compatible offsets -->
 *         </retry_schedule>
 *         <priorities>                     <!-- Set the default priority for this task. 50 is the default unless specified -->
 *           50,60,100                      <!-- If multiple values are given the next priority will be used on each retry -->
 *         </priorities>
 *         <after_success>keep</after_success>   <!-- If 'keep', the job will not be deleted after it is completed successfully -->
 *         <after_failure>delete</after_failure> <!-- If 'delete', the job will be deleted after failure (retries exhausted) -->
 *         <logging/>                       <!-- Enable or disable logging of errors -->
 *         <logfile>foo_queue.log</logfile> <!-- Set the logging destination. Defaults to mongo_queue_errors.txt -->
 *         <disabled/>                      <!-- Disable a specific task -->
 *         <disabled>false</disabled>       <!-- Override a previous "disabled" flag -->
 *         <groups>foo,bar</groups>         <!-- Assign to one or more groups so a task can be disabled with other tasks as a group -->
 *       </your_task_name>
 *       <your_task_namespace>
 *         <one>                            <!-- Task nested in a namespace: 'your_task_namespace/one' -->
 *           <type>helper</type>
 *           ...
 *         </one>
 *       </your_task_namespace>
 *     </tasks>
 *     <groups>
 *       <foo>
 *         <disabled/>  <!-- any tasks in "foo" group are disabled! -->
 *       </foo>
 *     </groups>
 *   </mongo_queue>
 * </config>
 *
 * Document Fields:
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
 *
 * @method Cm_Mongo_Model_Mongo_Job getResource()
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
    $retries = (int) $this->getRetries();

    // Update the execute_at time
    $time = new MongoDate;
    $schedule = (string) $this->getTaskConfig('retry_schedule');
    if($schedule) {
      $schedule = preg_split('/\s*,\s*/', $schedule, null, PREG_SPLIT_NO_EMPTY);
      $modifier = isset($schedule[$retries]) ? $schedule[$retries] : end($schedule);
      $time = new MongoDate(strtotime($modifier, $time->sec));
    }

    // Update the priority unless it was already set explicitly
    if( ! $this->dataHasChangedFor('priority')) {
      $priorities = (string) $this->getTaskConfig('priorities');
      $priorities = preg_split('/\s*,\s*/', $priorities, null, PREG_SPLIT_NO_EMPTY);
      if(count($priorities) > 1) {
        $this->setPriority(isset($priorities[$retries+1]) ? $priorities[$retries+1] : end($priorities));
      }
    }

    // Reset to ready status
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
    if( ! $this->getTaskConfig('class')) {
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
        $object->load($data->getData($loadIndex), $loadIndex);
        $data->unsetData($loadIndex);
        if( ! $object->getId()) {
          throw new Exception('Object could not be loaded.');
        }
      }

      $object->$method($data, $this);
      
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
    // Make sure a priority is set
    if($this->getData('priority') === null) {
      $priorities = $this->getTaskConfig('priorities');
      if($priorities === false) {
        $priority = Cm_Mongo_Model_Job::DEFAULT_PRIORITY;
      } else {
        $priorities = preg_split('/\s*,\s*/', $priorities, null, PREG_SPLIT_NO_EMPTY);
        $priority = $priorities[0];
      }
      $this->setData('priority', (int) $priority);
    }

    // Ensure that status updates are consistent
    if($this->isObjectNew() === false && $this->dataHasChangedFor('status')) {
      $this->setAdditionalSaveCriteria(array('status' => $this->getOrigData('status')));
    }
  }

  protected function _afterSave()
  {
    // Throw errors if a status update fails
    if($this->getLastUpdateStatus() === false) {
      throw new Exception("Failed to update job status to {$this->getStatus()} ({$this->getId()})");
    }
  }

}
