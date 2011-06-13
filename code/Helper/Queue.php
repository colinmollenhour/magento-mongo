<?php
/**
 * Contains helper methods for adding jobs to the queue and running queued jobs.
 */
class Cm_Mongo_Helper_Queue extends Mage_Core_Helper_Abstract
{

  /**
   * Add a job to the queue.
   *
   * @param string $task
   * @param array $data
   * @param int $priority  Optional override for priority
   * @param int|MongoDate $executeAt   Optional timestamp specifying when to be run
   * @return Cm_Mongo_Model_Job
   */
  public function addJob($task, $data, $priority = null, $executeAt = null)
  {
    $job = $this->_initNewJob($task, $data, $priority, $executeAt);
    $job->save();
    return $job;
  }

  /**
   * Add a job to the queue if it isn't already ready in the queue (upsert)
   *
   * @param string $task
   * @param array $data
   * @param int $priority  Optional override for priority
   * @param int|MongoDate $executeAt   Optional timestamp specifying when to be run
   * @return Cm_Mongo_Model_Job
   */
  public function addJobUnique($task, $data, $priority = null, $executeAt = null)
  {
    $job = $this->_initNewJob($task, $data, $priority, $executeAt);
    $job->isObjectNew(NULL); // force upsert
    $job->setAdditionalSaveCriteria(array(
        'status'   => Cm_Mongo_Model_Job::STATUS_READY,
        'task'     => $task,
        'job_data' => $data,
    ));
    $job->save();
    return $job;
  }

  /**
   * Add a job to the queue in a disabled state with a known _id, to be used in two-phase commits
   *
   * Enable the jobs using Mage::getResourceSingleton('mongo/job')->enableJobs($query);
   *
   * @param MongoId $id
   * @param string $task
   * @param array $data
   * @param int $priority  Optional override for priority
   * @param int|MongoDate $executeAt   Optional timestamp specifying when to be run
   * @return Cm_Mongo_Model_Job
   */
  public function addJobIdempotent(MongoId $id, $task, $data, $priority = null, $executeAt = null)
  {
    $job = $this->_initNewJob($task, $data, $priority, $executeAt);
    $job->setId($id);
    $job->setStatus(Cm_Mongo_Model_Job::STATUS_DISABLED);
    $job->isObjectNew(NULL); // force upsert
    $job->save();
    return $job;
  }

  /**
   * Add a job to the queue and attempt to run it immediately.
   *
   * If it was already picked up by the queue it will not be run but no error will be reported.
   *
   * @param string $task
   * @param array $data
   * @param boolean $throws
   * @return boolean
   */
  public function addAndRunJob($task, $data, $throws = FALSE)
  {
    try {
      $job = $this->addJob($task, $data);
      if( $job->getResource()->reloadJobForRunning($job) ) {
        $job->run();
      }
    }
    catch(Exception $e) {
      if($throws) {
        throw $e;
      } else {
        Mage::logException($e);
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Run the next jobs in the queue until the limit is reached
   *
   * @param int|null $limit      Maximum number of jobs to run
   * @param int|null $timeLimit  Maximum amount of time to run for
   * @return int the number of jobs executed
   */
  public function runQueue($limit = 100, $timeLimit = null)
  {
    if($stopTime = $timeLimit) {
      $stopTime += time();
    }
    for($i = 0; ( ! $limit || $i < $limit) && ( ! $stopTime || time() < $stopTime); $i++)
    {
      $job = Mage::getResourceSingleton('mongo/job')->getNextJob();
      if( ! $job) {
        break;
      }
      $job->run();
    }
    return $i;
  }

  /**
   * Initialize a new job instance
   *
   * @param string $task
   * @param array $data
   * @param int $priority  Optional override for priority
   * @param int|MongoDate $executeAt   Optional timestamp specifying when to be run
   * @return Cm_Mongo_Model_Job
   */
  protected function _initNewJob($task, $data, $priority = null, $executeAt = null)
  {
    $job = Mage::getModel('mongo/job')->setData(array(
        'status'     => Cm_Mongo_Model_Job::STATUS_READY,
        'task'       => $task,
        'job_data'   => $data,
        'priority'   => $priority,
        'execute_at' => $executeAt === null ? new MongoDate : $executeAt,
    ));
    return $job;
  }

}
