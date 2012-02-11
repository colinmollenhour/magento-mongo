<?php

class Cm_Mongo_Model_Pipeline
{

  /** @var Mongo_Database */
  protected $_connection;

  /** @var string */
  protected $_collection;

  /** @var array */
  protected $_pipeline = array();

  /**
   * @param Mongo_Database $connection
   * @param string $collection
   */
  public function __construct($connection, $collection)
  {
    if(is_array($connection)) {
      $connection = $connection[0];
      $collection = $collection[1];
    }
    $this->_connection = $connection;
    $this->_collection = $collection;
  }

  /**
   * @param array $pipeline
   */
  public function setPipeline($pipeline)
  {
    $this->_pipeline = $pipeline;
  }

  /**
   * @param array $pipeline
   */
  public function addPipeline($pipeline)
  {
    $this->_pipeline = array_merge($this->_pipeline, $pipeline);
  }

  /**
   * @return array
   */
  public function getPipeline()
  {
    return $this->_pipeline;
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function group($keyArrayJson, $value = NULL)
  {
    return $this->op('group', $keyArrayJson, $value);
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function limit($keyArrayJson, $value = NULL)
  {
    return $this->op('limit', $keyArrayJson, $value);
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function match($keyArrayJson, $value = NULL)
  {
    return $this->op('match', $keyArrayJson, $value);
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function project($keyArrayJson, $value = NULL)
  {
    return $this->op('project', $keyArrayJson, $value);
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function skip($keyArrayJson, $value = NULL)
  {
    return $this->op('skip', $keyArrayJson, $value);
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function sort($keyArrayJson, $value = NULL)
  {
    return $this->op('sort', $keyArrayJson, $value);
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function unwind($keyArrayJson, $value = NULL)
  {
    return $this->op('unwind', $keyArrayJson, $value);
  }

  /**
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function out($keyArrayJson, $value = NULL)
  {
    return $this->op('out', $keyArrayJson, $value);
  }

  /**
   * @param string $opName
   * @param string|array $keyArrayJson
   * @param null|string|array $value
   * @return Cm_Mongo_Model_Pipeline
   */
  public function op($opName, $keyArrayJson, $value = NULL)
  {
    if($value === NULL) {
      if(is_string($keyArrayJson)) {
        $arg = json_decode($keyArrayJson, TRUE);
      }
      else {
        $arg = $keyArrayJson;
      }
    }
    else {
      $arg = array($keyArrayJson => $value);
    }
    $this->_pipeline[] = array('$'.$opName => $arg);
    return $this;
  }

  /**
   * @return array
   * @throws Exception
   */
  public function execute()
  {
    $result = $this->_connection->command(array(
      'aggregate' => $this->_collection,
      'pipeline' => $this->_pipeline,
    ));
    if($result['ok'] != 1) {
      throw new Exception('Error executing aggregate command: '.$result['ok']);
    }
    return $result['result'];
  }

  /**
   * @param string $resourceName
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function loadAs($resourceName)
  {
    $collection = Mage::getResourceModel($resourceName); /* @var $collection Cm_Mongo_Model_Resource_Collection_Abstract */
    $collection->loadAggregate($this);
    return $collection;
  }

}
