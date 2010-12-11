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
    require_once 'mongodb-php-odm'.DS.'classes'.DS.'mongo'.DS.'database.php';
    require_once 'mongodb-php-odm'.DS.'classes'.DS.'mongo'.DS.'collection.php';
    
    $conn = Mongo_Database::instance($config['config'], $config);
    // @TODO - set profiler

    return $conn;
  }

}
