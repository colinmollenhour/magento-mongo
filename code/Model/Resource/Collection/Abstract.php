<?php

class Cm_Mongo_Model_Resource_Collection_Abstract extends Varien_Data_Collection
{

  /** The resource model name
   * @var string */
  protected $_resourceModel;

  /** The resource model instance
   * @var Cm_Mongo_Model_Resource_Abstract */
  protected $_resource;

  /** The mongo database connection instance
   * @var Mongo_Database */
  protected $_conn;

  /** The mongo collection instance used as a query object
   * @var Mongo_Collection */
  protected $_query;
  
  /** Storage for the raw data
   * @var array */
  protected $_data;
  
  public function __construct($resource = NULL)
  {
    parent::__construct();
    $this->_construct();
    $this->_resource = $resource;
    $this->setConnection($this->getResource()->getReadConnection());
  }
  
  public function _construct()
  {
  }

  /**
   * Standard resource collection initalization
   *
   * @param string $model
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _init($model, $resourceModel=null)
  {
    $this->setItemObjectClass(Mage::getConfig()->getModelClassName($model));
    if (is_null($resourceModel)) {
      $resourceModel = $model;
    }
    $this->_resourceModel = $resourceModel;
    return $this;
  }

  /**
  * Get resource instance
  *
  * @return Cm_Mongo_Model_Resource_Abstract
  */
  public function getResource()
  {
    if (empty($this->_resource)) {
      $this->_resource = Mage::getResourceModel($this->_resourceModel);
    }
    return $this->_resource;
  }

  /**
   * Get the collection name for this collection resource model
   *
   * @param  string  The entity name to get the collection name for (defaults to current entity)
   * @return string
   */
  public function getCollectionName($entityName = NULL)
  {
    return $this->getResource()->getCollectionName($entityName);
  }

  /**
   * The id field name (_id)
   * 
   * @return string
   */
  public function getIdFieldName()
  {
    return $this->getResource()->getIdFieldName();
  }
  
  /**
   * Get the database connection
   *
   * @return Mongo_Database
   */
  public function getConnection()
  {
    return $this->_conn;
  }

  /**
   * Get the mongo collection instance which is used as a query object
   *
   * @return Mongo_Collection
   */
  public function getQuery()
  {
    return $this->_query;
  }
  
  /**
   * Strictly for backwards compatibility with MySQL
   * 
   * @return Mongo_Collection
   */
  public function getSelect()
  {
    return $this->getQuery();
  }

  /**
   * Set the mongo database instance
   *
   * @param Mongo_Database $conn
   * @return Cm_Mongo_Model_Resource_Abstract
   */
  public function setConnection($conn)
  {
    $this->_conn = $conn;
    $this->_query = $this->_conn->selectCollection($this->getCollectionName());
    return $this;
  }

  /**
   * Get collection size
   *
   * @return int
   */
  public function getSize()
  {
    if (is_null($this->_totalRecords)) {
      $this->_renderFilters();
      $this->_totalRecords = $this->getQuery()->count(FALSE); // false ignores limit and skip
    }
    return intval($this->_totalRecords);
  }

  /**
   * Get SQL for get record count
   *
   * @return Mongo_Collection
   */
  public function getCountQuery()
  {
    $this->_renderFilters();

    $query = clone $this->getQuery();
    $query->unset_option('sort')
          ->unset_option('limit')
          ->unset_option('skip');
    return $query;
  }

  /**
   * Before load action
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _beforeLoad()
  {
    return $this;
  }

  /**
   * Render find conditions
   *
   * @return  Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _renderFilters()
  {
    if ($this->_isFiltersRendered) {
      return $this;
    }
    foreach ($this->_filters as $filter) {
      switch ($filter['type']) {
        case 'or' :
          $this->_query->find('$or', array($filter['field'] => $filter['value']));
          break;
        case 'string' :
          $this->_query->find('$where', $filter['value']);
          break;
        case 'and':
        default:
          $this->_query->find($filter['field'], $filter['value']);
      }
    }
    $this->_isFiltersRendered = true;
    return $this;
  }

  /**
   * Add cursor order
   *
   * @param   string $field
   * @param   string $direction
   * @return  Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function setOrder($field, $direction = self::SORT_ORDER_DESC)
  {
    return $this->_setOrder($field, $direction);
  }

  /**
   * self::setOrder() alias
   *
   * @param string $field
   * @param string $direction
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addOrder($field, $direction = self::SORT_ORDER_DESC)
  {
    return $this->_setOrder($field, $direction);
  }

  /**
   * Add cursor order to the beginning
   *
   * @param string $field
   * @param string $direction
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function unshiftOrder($field, $direction = self::SORT_ORDER_DESC)
  {
    return $this->_setOrder($field, $direction, true);
  }

  /**
   * Add sort order to the end or to the beginning
   *
   * @param string $field
   * @param string $direction
   * @param bool $unshift
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _setOrder($field, $direction, $unshift = false)
  {
    $direction = (strtoupper($direction) == self::SORT_ORDER_ASC) ? Mongo_Collection::ASC : Mongo_Collection::DESC;

    // emulate associative unshift
    if ($unshift) {
      $orders = array($field => $direction);
      foreach ((array)$this->_query->get_option('sort') as $key => $_direction) {
        if (!isset($orders[$key])) {
          $orders[$key] = $_direction;
        }
      }
      $this->_query->set_option('sort', $orders);
    }
    else {
      $this->_query->sort($field, $direction);
    }
    return $this;
  }

  /**
   * Render sql select orders
   *
   * @return  Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _renderOrders()
  {
    // already rendered
    return $this;
  }

  /**
   * Render sql select limit
   *
   * @return  Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _renderLimit()
  {
    if($this->_pageSize){
      if($this->getCurPage() > 1) {
        $this->_query->skip(($this->getCurPage()-1) * $this->_pageSize);
      }
      $this->_query->limit($this->_pageSize);
    }
    return $this;
  }

  /**
   * Load data
   *
   * @param  boolean  $printQuery
   * @param  boolean  $logQuery
   * @return  Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function load($printQuery = false, $logQuery = false)
  {
    if ($this->isLoaded()) {
      return $this;
    }

    $documents = $this->getData();
    $this->printLogQuery($printQuery, $logQuery);
    $this->resetData();

    if (is_array($documents)) {
      $idFieldName = $this->getIdFieldName();
      foreach ($documents as $data) {
        $item = $this->getNewEmptyItem();
        if ($idFieldName) {
          $item->setIdFieldName($idFieldName);
        }
        $this->getResource()->hydrate($item, $data);
        $item->setOrigData();
        $this->addItem($item);
      }
    }

    $this->_setIsLoaded();
    $this->_afterLoad();
    return $this;
  }

  /**
   * Get all data array for collection
   *
   * @return array
   */
  public function getData()
  {
    if ($this->_data === null) {
      $this->_beforeLoad();
      $this->_renderFilters()
           ->_renderOrders()
           ->_renderLimit();
      $this->_data = $this->_query->as_array(TRUE);
      $this->_afterLoadData();
    }
    return $this->_data;
  }

  /**
   * Process loaded collection data
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _afterLoadData()
  {
    return $this;
  }

  /**
   * Reset loaded for collection data array
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function resetData()
  {
    $this->_data = null;
    return $this;
  }

  /**
   * Reset collection
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _reset()
  {
    $this->getQuery()->reset();
    $this->_setIsLoaded(false);
    $this->_items = array();
    $this->_data = null;
    return $this;
  }

  /**
   * After load action
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _afterLoad()
  {
    return $this;
  }

  /**
   * Print and/or log query
   *
   * @param boolean $printQuery
   * @param boolean $logQuery
   * @return  Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function printLogQuery($printQuery = false, $logQuery = false, $sql = null) {
    if ($printQuery) {
      echo $this->getQuery()->inspect();
    }
    if ($logQuery){
      Mage::log($this->getQuery()->inspect());
    }
    return $this;
  }

  /**
   * Add field filter to collection
   *
   * @param string $field
   * @param null|string|array $condition
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addFieldToFilter($field, $condition=null)
  {
    $this->_query->find($this->_getCondition($field, $condition));
    return $this;
  }

  /**
   * Build query condition
   *
   * If $condition is not array - exact value will be filtered
   *
   * If $condition is assoc array - one of the following structures is expected:
   * - array("from"=>$fromValue, "to"=>$toValue)
   * - array("like"=>$likeValue)
   * - array("notnull"=>TRUE)
   *
   * If $condition is numerically indexed array then treated as $or conditions
   *
   * @param string $fieldName
   * @param integer|string|array $condition
   * @return array
   */
  protected function _getCondition($fieldName, $condition)
  {
    if (is_array($condition)) {
      if (isset($condition['from']) || isset($condition['to'])) {
        $query = array();
        if (isset($condition['from'])) {
          if (empty($condition['date'])) {
            if ( empty($condition['datetime'])) {
              $from = $condition['from'];
            }
            else {
              $from = new MongoDate(strtotime($condition['from']));
            }
          }
          else {
            $from = new MongoDate($condition['from'].' 00:00:00');
          }
          $query[$fieldName] = array('$gte' => $from);
        }
        if (isset($condition['to'])) {
          if (empty($condition['date'])) {
            if ( empty($condition['datetime'])) {
              $to = $condition['to'];
            }
            else {
              $to = new MongoDate(strtotime($condition['to']));
            }
          }
          else {
            $to = new MongoDate($condition['to'].' 00:00:00');
          }
          $query[$fieldName] = array('$lte' => $to);
        }
      }
      elseif (isset($condition['eq'])) {
        $query = array($fieldName => $condition['eq']);
      }
      elseif (isset($condition['neq'])) {
        $query = array($fieldName => array('$ne' => $condition['neq']));
      }
      elseif (isset($condition['like'])) {
        $query = $condition['like'];
        if(strlen($query) && $query{0} != '%') {
          $query = '^'.$query;
        }
        if(strlen($query) && substr($query,-1) != '%') {
          $query = $query.'$';
        }
        $query = trim($query,'%');
        $query = array($fieldName => new MongoRegex('/'.str_replace(array('%','/'),array('.*','\/'),$query).'/i'));
      }
      elseif (isset($condition['nlike'])) {
        $query = $condition['nlike'];
        if(strlen($query) && $query{0} != '%') {
          $query = '^'.$query;
        }
        if(strlen($query) && substr($query,-1) != '%') {
          $query = $query.'$';
        }
        $query = trim($query,'%');
        $query = array($fieldName => array('$not' => new MongoRegex('/'.str_replace(array('%','/'),array('.*','\/'),$query).'/i')));
      }
      elseif (isset($condition['notnull'])) {
        $query = array($fieldName => array('$not' => array('$type' => 10)));
      }
      elseif (isset($condition['null'])) {
        $query = array($fieldName => array('$type' => 10));
      }
      elseif (isset($condition[0])) {
        $query = array();
        foreach ($condition as $orCondition) {
          $query[] = $this->_getCondition($fieldName, $orCondition);
        }
        $query = array('$or' => $query);
      }
      else {
        $query = array($fieldName => $condition);
      }
    } else {
      $query = array($fieldName => $condition);
    }
    return $query;
  }

  /**
   * Save all the entities in the collection
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function save()
  {
    foreach ($this->getItems() as $item) {
      $item->save();
    }
    return $this;
  }

}
