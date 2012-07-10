<?php
/**
 * Loads the database driver
 */
class Cm_Mongo_Model_Resource_Type_Mongo extends Mage_Core_Model_Resource_Type_Abstract
{
  /**
   * Get the Mongo database adapter
   *
   * @param array $config Connection config
   * @return Mongo_Database
   */
  public function getConnection(array $config)
  {
    $name = isset($config['config']) ? $config['config'] : '';
    $conn = Mongo_Database::instance($name, $config);

    // Set profiler
    $conn->set_profiler(array($this, 'start_profiler'), array($this, 'stop_profiler'));

    return $conn;
  }

  /**
   * @param string $group
   * @param string $query
   * @return string
   */
  public function start_profiler($group, $query)
  {
    $key = "$group::$query";
    Cm_Mongo_Profiler::start($key);
    return $key;
  }

  /**
   * @param string $key
   */
  public function stop_profiler($key)
  {
    Cm_Mongo_Profiler::stop($key);
  }

}
