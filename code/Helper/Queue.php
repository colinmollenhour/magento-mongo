<?php

class Cm_Mongo_Helper_Queue extends Mage_Core_Helper_Abstract
{

  /**
   * Add a job to the queue.
   *
   * @param string $task
   * @param array $data
   * @param int $priority  Option override for priority
   * @return Cm_Mongo_Model_Job
   */
  public function addJob($task, $data, $priority = null)
  {
    $job = Mage::getModel('mongo/job')->setData(array(
        'status'     => Cm_Mongo_Model_Job::STATUS_READY,
        'task'       => $task,
        'job_data'   => $data,
        'priority'   => $priority,
        'execute_at' => time(),
    ));
    $job->save();
    return $job;
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
