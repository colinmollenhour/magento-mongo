<?php

require_once 'app/Mage.php';

class Queue_Daemon {

  /** @var \Mongo_Database */
  protected $db;

  /** @var \Mongo_Collection */
  protected $coll;

  /** @var \MongoId */
  protected $id;

  /** @var array */
  protected $workers;

  protected $nextExecution;
  protected $minWorkers;
  protected $maxWorkers;
  protected $maxJobs;
  protected $cmd;

  public function __construct($id, $config)
  {
    $this->db = new Mongo_Database('default', $config);
    $this->coll = $this->db->selectCollection('queue_daemon');
    $this->id = $id;
  }

  public function run($minWorkers, $maxWorkers, $maxJobs)
  {
    $this->minWorkers = $minWorkers;
    $this->maxWorkers = $maxWorkers;
    $this->maxJobs = $maxJobs;
    $this->updateNextExecution();
    $this->coll->insert(array(
      '_id'            => $this->id,
      'next_execution' => $this->nextExecution,
      'workers'        => array(),
      'idle'           => 0,
      'busy'           => 0,
      'total'          => 0,
      'min'            => $minWorkers,
      'max'            => $maxWorkers,
    ));
    for($i = 0; $i < $minWorkers; $i++) {
      $this->spawnWorker();
    }
    // TODO - setup tail
    while(1) {
      if(1) {
        $executeAt = strtotime('+1 seconds'); // TODO - retrieve from oplog
        if($executeAt < $this->nextExecution) {
          $this->nextExecution = $executeAt;
          if( ! $this->wakeIdleWorker()) {
            $this->spawnWorker();
          }
        }
      }
    }
  }

  public function shutdown()
  {
    $this->coll->update(array('_id' => $this->id), array('$set' => array('status' => 'dieing')));
    foreach($this->workers as $workerId) {
      // TODO - soft kill $workerId
    }
    $this->coll->update(array('_id' => $this->id), array('$set' => array('status' => 'dead')));
    exit(0);
  }

  public function kill()
  {
    $this->coll->update(array('_id' => $this->id), array('$set' => array('status' => 'dieing')));
    foreach($this->workers as $workerId) {
      // TODO - kill -9 $workerId
    }
    $this->coll->update(array('_id' => $this->id), array('$set' => array('status' => 'dead')));
    exit(0);
  }

  /**
   * @return bool
   */
  protected function spawnWorker()
  {
    $newDoc = $this->coll->findAndModify(array(
      'query' => array('_id' => $this->id, 'total' => array('$lte' => $this->maxWorkers)),
      'update' => array('$inc' => array('busy' => 1, 'total' => 1)),
      'new' => TRUE,
    ));
    if($newDoc) {
      $this->updateInfo($newDoc);
      $workerId = new MongoId;
      // TODO - spawn process
      $proc = proc_open("$this->cmd --id $workerId --min-workers $this->minWorkers", array(), $pipes, '', array());
      $procInfo = proc_get_status($proc);
      $this->workers["$workerId"] = $procInfo['pid'];
      return TRUE;
    }
    return FALSE;
  }

  /**
   * @return bool
   * @throws Exception
   */
  protected function wakeIdleWorker()
  {
    $oldDoc = $this->coll->findAndModify(array(
      'query' => array('_id' => $this->id, 'idle' => array('$gt' => 0)),
      'update' => array(
        '$inc' => array('busy' => 1, 'idle' => -1),
        '$pop' => array('workers' => -1),
        '$unset' => array('dead_workers' => 1),
      ),
      'new' => FALSE,
    ));
    if($oldDoc) {
      $this->updateInfo($oldDoc);
      $workerId = $oldDoc['workers'][0];
      $pid = $this->workers["$workerId"];
      if( ! posix_kill($pid, SIGALRM)) {
        $errmsg = posix_strerror(posix_get_last_error());
        throw new Exception($errmsg);
      }
      return TRUE;
    }
    return FALSE;
  }

  protected function updateNextExecution()
  {
    $nextJob = $this->db->selectCollection('job_queue')->findOne(array(), array('execute_at' => 1));
    if($nextJob) {
      $this->nextExecution = $nextJob['execute_at'];
    } else {
      $this->nextExecution = strtotime('+1 year');
    }
  }

  /**
   * @param $doc
   */
  protected function updateInfo($doc)
  {
    $this->minWorkers = $doc['min'];
    $this->maxWorkers = $doc['max'];
    if($doc['dead_workers']) {
      foreach($doc['dead_workers'] as $workerId) {
        unset($this->workers["$workerId"]);
      }
    }
  }

}
$daemon = new Queue_Daemon('default', array());
$daemon->run(1, 4, 1000);
