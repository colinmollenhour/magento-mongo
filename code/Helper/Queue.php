<?php

class Cm_Mongo_Helper_Queue extends Mage_Core_Helper_Abstract
{

  /**
   * Add a job to the queue.
   *
   * @param string $task
   * @param array $data
   * @param int $priority  Optional override for priority
   * @param int $executeAt   Optional timestamp specifying when to be run
   * @return Cm_Mongo_Model_Job
   */
  public function addJob($task, $data, $priority = null, $executeAt = null)
  {
    $job = Mage::getModel('mongo/job')->setData(array(
        'status'     => Cm_Mongo_Model_Job::STATUS_READY,
        'task'       => $task,
        'job_data'   => $data,
        'priority'   => $priority,
        'execute_at' => $executeAt === NULL ? time() : $executeAt,
    ));
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
      $job = $this->addJob($task, $data, $priority);
      if( $job->getResource()->loadJobForRunning($job) ) {
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
   * @param int $limit
   * @param int $time
   * @return int the number of jobs executed
   */
  public function runQueue($limit = 100, $time = null)
  {
    if($time) {
      $stopTime = time() + $time;
    }
    for($i = 0; ( ! $limit || $i < $limit) && ( ! $time || time() >= $stopTime); $i++)
    {
      $job = Mage::getResourceSingleton('mongo/job')->getNextJob();
      if( ! $job) {
        break;
      }
      $job->run();
    }
    return $i;
  }

}
