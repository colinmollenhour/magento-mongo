<?php

class Cm_Mongo_Model_Resource_Setup extends Mage_Core_Model_Resource_Setup
{

  /** @var Mongo_Database */
  protected $_mongoConn;

  /**
   * Sets _conn to the SQL connection and saves Mongo connection as _mongoConn
   *
   * @param string $resourceName
   */
  public function __construct($resourceName)
  {
    parent::__construct($resourceName);
    $this->_mongoConn = $this->_conn;
    $this->_conn = Mage::getSingleton('core/resource')->getConnection('core_setup');
  }

  /**
   * @return Mongo_Database
   */
  public function getMongoDatabase()
  {
    return $this->_mongoConn;
  }

  /**
   * Run javascript through the mongo shell (uses temporary file and exec)
   *
   * @param string $js
   * @return Cm_Mongo_Model_Resource_Setup
   */
  public function runJs($js)
  {
    $filename = tempnam(sys_get_temp_dir(), 'magento').'.js';
    file_put_contents($filename, $js);
    $output = shell_exec("mongo --quiet {$this->_connectionConfig->server}/{$this->_connectionConfig->database} $filename");
    unlink($filename);
    if($output) {
      throw new Exception($output);
    }
    return $this;
  }

  /**
   * Run module modification files. Return version of last applied upgrade (false if no upgrades applied)
   *
   * @param   string $actionType install|upgrade|uninstall
   * @param   string $fromVersion
   * @param   string $toVersion
   * @return  string | false
   */

  protected function _modifyResourceDb($actionType, $fromVersion, $toVersion)
  {
    $resModel = (string)$this->_connectionConfig->model;
    $modName = (string)$this->_moduleConfig[0]->getName();

    $sqlFilesDir = Mage::getModuleDir('sql', $modName).DS.$this->_resourceName;
    if (!is_dir($sqlFilesDir) || !is_readable($sqlFilesDir)) {
      return false;
    }
    // Read resource files
    $arrAvailableFiles = array();
    $sqlDir = dir($sqlFilesDir);
    while (false !== ($sqlFile = $sqlDir->read())) {
      $matches = array();
      if (preg_match('#^'.$resModel.'-'.$actionType.'-(.*)\.(js|php)$#i', $sqlFile, $matches)) {
        $arrAvailableFiles[$matches[1]] = $sqlFile;
      }
    }
    $sqlDir->close();
    if (empty($arrAvailableFiles)) {
      return false;
    }

    // Get SQL files name
    $arrModifyFiles = $this->_getModifySqlFiles($actionType, $fromVersion, $toVersion, $arrAvailableFiles);
    if (empty($arrModifyFiles)) {
      return false;
    }

    $modifyVersion = false;
    foreach ($arrModifyFiles as $resourceFile) {
      $sqlFile = $sqlFilesDir.DS.$resourceFile['fileName'];
      $fileType = pathinfo($resourceFile['fileName'], PATHINFO_EXTENSION);
      // Execute SQL
      if ($this->_conn) {
        if (method_exists($this->_conn, 'disallowDdlCache')) {
          $this->_conn->disallowDdlCache();
        }
        try {
          switch ($fileType) {
            case 'js':
              $result = true;
              $output = shell_exec("mongo --quiet {$this->_connectionConfig->server}/{$this->_connectionConfig->database} $sqlFile");
              if($output) {
                throw new Exception($output);
              }
              break;
            case 'php':
              $result = include($sqlFile);
              break;
            default:
              $result = false;
          }
          if ($result) {
            if (strpos($actionType, 'data-') !== false) {
              $this->_getResource()->setDataVersion($this->_resourceName, $resourceFile['toVersion']);
            } else {
              $this->_getResource()->setDbVersion($this->_resourceName, $resourceFile['toVersion']);
            }
          }
        } catch (Exception $e){
          echo "<pre>".print_r($e,1)."</pre>";
          throw Mage::exception('Mage_Core', Mage::helper('core')->__('Error in file: "%s" - %s', $sqlFile, $e->getMessage()));
        }
        if (method_exists($this->_conn, 'allowDdlCache')) {
          $this->_conn->allowDdlCache();
        }
      }
      $modifyVersion = $resourceFile['toVersion'];
    }
    self::$_hadUpdates = true;
    return $modifyVersion;
  }

}
