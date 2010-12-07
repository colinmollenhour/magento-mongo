<?php

class Cm_Mongo_Model_Resource_Type_Mongo extends Mage_Core_Model_Resource_Type_Abstract
{

  /**
   * Get the Mongo database adapter
   *
   * @param array $config Connection config
   * @return Mongo_Database
   */
  public function getConnection($config)
  {
    $configArr = (array)$config;
    $configArr['profiler'] = !empty($configArr['profiler']) && $configArr['profiler']!=='false';

    $conn = Mongo_Database::instance($configArr['config'], $configArr);

    return $conn;
  }

}
