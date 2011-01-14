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

  /** List of fields to be preloaded after load
   * @var array */
  protected $_preloadFields = array();

  /** References to collections that have been preloaded
   * @var array */
  protected $_referencedCollections = array();
  
  public function __construct($resource = NULL)
  {
    parent::__construct();
    $this->_construct();
    $this->_resource = $resource;
    $this->_conn = $this->getResource()->getReadConnection();
    $this->_query = $this->_conn->selectCollection($this->getCollectionName());
  }
  
  /**
   * Overload to perform initialization
   */
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
   * Add field filter to collection.
   *
   * If $field is an array then each key => value is applied as a separate condition.
   *
   * Filter by other collection value using the -> operator to separate the
   * field that references the other collection and the field in the other collection.
   * - ('bar_id->name', 'eq', 'Baz')
   *
   * If $condition is not array - exact value will be filtered
   *
   * If $condition is assoc array - one of the following structures is expected:
   * - array("from"=>$fromValue, "to"=>$toValue)
   * - array("eq|neq"=>$likeValue)
   * - array("like|nlike"=>$likeValue)
   * - array("null|notnull"=>TRUE)
   * - array("is"=>"NULL|NOT NULL")
   * - array("in|nin"=>$array)
   * - array("$__"=>$value) - Any mongo operator
   *
   * If $condition is a numerically indexed array then it is treated as $or conditions
   *
   * @param string $field
   * @param null|string|array $condition
   * @param null|string|array $condition
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addFieldToFilter($field, $condition=null, $_condition=null)
  {
    if (is_array($field)) {
      foreach($field as $fieldName => $condition) {
        $this->_query->find($this->_getCondition($field, $condition));
      }
    }
    else {
      $this->_query->find($this->_getCondition($field, $condition, $_condition));
    }
    return $this;
  }

  /**
   * Adds a field to be preloaded after the collection is loaded.
   * If the collection is already loaded it will preload the referenced
   * collection immediately.
   *
   * @param string|array $field
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addFieldToPreload($field)
  {
    if(is_array($field)) {
      foreach($field as $_field) {
        $this->_preloadFields[$field] = TRUE;
      }
    }
    else {
      $this->_preloadFields[$field] = TRUE;
    }

    if($this->isLoaded()) {
      $this->_loadReferences();
    }

    return $this;
  }

  /**
   * Add (or remove) fields to query results.
   *
   * @param string|array $field
   * @param boolean $include
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addFieldToResults($field, $include = 1)
  {
    if( ! is_array($field)) {
      $field = array($field => $include);
    }
    $include = (int) $include;

    $this->_query->fields($fields, $include);

    return $this;
  }

  /**
   * Cast values to the proper type before running query
   *
   * @param string $field
   * @param mixed $value
   * @return mixed
   */
  public function castFieldValue($field, $value)
  {
    return $this->getResource()->castToMongo($field, $value);
  }

  /**
   * Get referenced objects for the current query. Triggers a load if not already loaded.
   * 
   * Returns the referenced collection unloaded so that additional filters/parameters may be set.
   *
   * @param string $field
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function getReferencedCollection($field)
  {
    if(empty($this->_referencedCollections[$field]))
    {
      $ids = array();

      // Get all ids for the given field
      $fieldType = (string) $this->getResource()->getFieldMapping($field)->type;
      if($fieldType == 'reference') {
        foreach($this->getItems() as $item) {
          if($ref = $item->getData($field)) {
            $ids[] = $ref;
          }
        }
      }
      // Get unique set of ids from field
      else if($fieldType == 'referenceSet') {
        foreach($this->getItems() as $item) {
          if($refSet = $item->getData($field)) {
            foreach($refSet as $ref) {
              $ids[] = $ref;
            }
          }
        }
      }
      else {
        throw new Mage_Core_Exception("Cannot get referenced collection for field '$field' of type '$fieldype'.");
      }

      // array_unique is slow, but required for compatibility with MongoId and possibly other id data types
      $ids = array_unique($ids);
    
      // Instantiate a collection filtered to the referenced objects using $in
      $modelName = $this->getResource()->getFieldModelName($field);
      $collection = Mage::getSingleton($modelName)->getCollection();
      $collection->addFieldToFilter('_id', '$in', $ids);
      $this->_referencedCollections[$field] = $collection;
    }
    return $this->_referencedCollections[$field];
  }

  /**
   * Get resource instance
   *
   * @return Cm_Mongo_Model_Resource_Abstract
   */
  public function getResource()
  {
    if (empty($this->_resource)) {
      $this->_resource = Mage::getResourceSingleton($this->_resourceModel);
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
   * Get collection size (ignoring limit and skip)
   *
   * @return int
   */
  public function getSize()
  {
    if (is_null($this->_totalRecords)) {
      $this->_totalRecords = $this->getQuery()->count(FALSE); // false ignores limit and skip
    }
    return intval($this->_totalRecords);
  }

  /**
   * Get all _id's for the current query. If $noLoad is true and the collection is not already loaded
   * then a query will be run returning only the _ids and no objects will be hydrated.
   * 
   * @param boolean $noLoad
   * @return array
   */
  public function getAllIds($noLoad = FALSE)
  {
    if($this->isLoaded() || ! $noLoad) {
      return parent::getAllIds();
    }

    // Use fast method of getting ids, full documents not loaded
    $idsQuery = clone $this->_query;
    $idsQuery->set_option('fields', array('_id' => 1));
    $ids = array();
    foreach($idsQuery->cursor() as $key => $document) {
      $ids[] = $document['_id'];
    }
    return $ids;
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
    $this->resetData();

    if($printQuery || $logQuery) {
      $this->debugQuery($printQuery, $logQuery);
    }

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
    $this->_loadReferences();
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
    if ($this->_data === null)
    {
      $this->_beforeLoad();
      $this->_renderLimit();
      $this->_renderFields();
      $this->_data = $this->_query->as_array(FALSE);
      $this->_afterLoadData();
    }
    return $this->_data;
  }

  /**
   * @param boolean $printQuery
   * @param boolean $logQuery
   * @return  string
   */
  public function debugQuery($printQuery = false, $logQuery = false)
  {
    $query = $this->getQuery()->inspect();
    if ($printQuery) {
      echo $query;
    }
    if ($logQuery){
      Mage::log($query);
    }
    return $query;
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
   * Reset collection to fresh state.
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function reset()
  {
    $this->getQuery()->reset();
    $this->_setIsLoaded(false);
    $this->_items = array();
    $this->_data = null;
    $this->_preloadFields = array();
    $this->_referencedCollections = array();
    return $this;
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
   * Process loaded collection data
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _afterLoadData()
  {
    return $this;
  }

  /**
   * Load references
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _loadReferences()
  {
    foreach($this->_preloadFields as $field => $true)
    {
      if(isset($this->_referencedCollections[$field])) {
        continue;
      }
      $collection = $this->getReferencedCollection($field);
      $modelName = $this->getResource()->getFieldModelName($field);
      Mage::getResourceSingleton($modelName)->addCollectionToCache($collection);
    }
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
   * Build query condition. See docs for addFieldToFilter.
   *
   * @param string $fieldName
   * @param integer|string|array $condition
   * @return array
   */
  protected function _getCondition($fieldName, $condition, $_condition)
  {
    // Handle cross-collection filters with field names like bar_id:name
    if(strpos($fieldName, '->')) {
      list($reference,$referenceField) = explode('->', $fieldName);
      //$this->getResource()->getFieldModelName($reference)
      $collection = Mage::getSingleton($this->getResource()->getFieldModelName($reference))->getCollection();
      $collection->addFieldToFilter($referenceField, $condition, $_condition);
      $query = array($reference => array('$in' => $collection->getAllIds(TRUE)));
    }

    // When using third argument, no type casting is performed
    else if ( $_condition !== NULL) {
      $query = array($fieldName => array($condition => $_condition));
    }

    // Process special condition keys
    else if (is_array($condition)) {
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
            $from = new MongoDate(strtotime($condition['from'].' 00:00:00'));
          }
          $query['$gte'] = $this->castFieldValue($fieldName, $from);
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
            $to = new MongoDate(strtotime($condition['to'].' 00:00:00'));
          }
          $query['$lte'] = $this->castFieldValue($fieldName, $to);
        }
        $query = array($fieldName => $query);
      }
      elseif (isset($condition['eq'])) {
        // Search array for presence of a single value
        if( ! is_array($condition['eq']) && $this->getResource()->getFieldMapping($fieldName)->type == 'set') {
          $query = array($fieldName => $condition['eq']);
        }
        // Search for an exact match
        else {
          $query = array($fieldName => $this->castFieldValue($fieldName, $condition['eq']));
        }
      }
      elseif (isset($condition['neq'])) {
        $query = array($fieldName => array('$ne' => $this->castFieldValue($fieldName, $condition['neq'])));
      }
      elseif (isset($condition['like'])) {
        $query = preg_quote($condition['like']);
        $query = str_replace('\_', '_', $query); // unescape SQL syntax
        if(strlen($query) && $query{0} != '%') {
          $query = '^'.$query;
        }
        if(strlen($query) && substr($query,-1) != '%') {
          $query = $query.'$';
        }
        $query = trim($query,'%');
        $query = array($fieldName => new MongoRegex('/'.str_replace('%','.*',$query).'/i'));
      }
      elseif (isset($condition['nlike'])) {
        $query = preg_quote($condition['nlike']);
        $query = str_replace('\_', '_', $query); // unescape SQL syntax
        if(strlen($query) && $query{0} != '%') {
          $query = '^'.$query;
        }
        if(strlen($query) && substr($query,-1) != '%') {
          $query = $query.'$';
        }
        $query = trim($query,'%');
        $query = array($fieldName => array('$not' => new MongoRegex('/'.str_replace('%','.*',$query).'/i')));
      }
      elseif (isset($condition['notnull'])) {
        $query = array($fieldName => array('$not' => array('$type' => Mongo_Database::TYPE_NULL)));
      }
      elseif (isset($condition['null'])) {
        $query = array($fieldName => array('$type' => Mongo_Database::TYPE_NULL));
      }
      elseif (isset($condition['is'])) {
        $query = strtoupper($condition['is']);
        if($query == 'NULL' || $query === NULL) {
          $query = array($fieldName => array('$type' => Mongo_Database::TYPE_NULL));
        } else if($query == 'NOT NULL') {
          $query = array($fieldName => array('$not' => array('$type' => Mongo_Database::TYPE_NULL)));
        }
      }
      elseif (isset($condition['in'])) {
        $values = array();
        foreach($condition['in'] as $value) {
          $values[] = $this->castFieldValue($fieldName, $value);
        }
        $query = array($fieldName => array('$in' => $values));
      }
      elseif (isset($condition['nin'])) {
        $values = array();
        foreach($condition['nin'] as $value) {
          $values[] = $this->castFieldValue($fieldName, $value);
        }
        $query = array($fieldName => array('$nin' => $values));
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
    }

    // Condition is scalar
    else {
      $query = array($fieldName => $condition);
    }
    return $query;
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
   * Apply the limit and skip based on paging variables
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
   * Make sure that preloaded fields are included in query
   */
  protected function _renderFields()
  {
    $fields = $this->_query->get_option('fields');
    if($fields && array_sum($fields)) {
      foreach($this->_preloadFields as $field => $true) {
        $fields[$field] = 1;
      }
      $this->_query->set_option('fields', $fields);
    }
  }

}
