<?php
/**
 * The Cm_Mongo equivalent of Mage_Core_Model_Mysql4_Abstract
 */
class Cm_Mongo_Model_Resource_Collection_Abstract extends Cm_Mongo_Collection
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

  /** Indicates if collection is being iterated using getNextItem
   * @var bool */
  protected $_iterating = FALSE;

  /** Storage for the raw data
   * @var array */
  protected $_data;

  /** List of fields to be preloaded after load
   * @var array */
  protected $_preloadFields = array();

  /** References to collections that have been preloaded
   * @var array */
  protected $_referencedCollections = array();

  /**
   * @param null|Cm_Mongo_Model_Resource_Abstract $resource
   */
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
   * Standard resource collection initialization
   *
   * @param string $model
   * @param null $resourceModel
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
   * Add aggregate data, such as from a group operation.
   *
   * $keys is in the form of:
   * '_id' => 'ref_id'  (index in this collection, index in aggregate data)
   *
   * @param array $keys
   * @param array $data
   * @param array $defaults   Optionally specify default values for missing data
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addAggregateData($keys, &$data, $defaults = array())
  {
    // Multi-key join
    if(count($keys) > 1) {
      // todo, support adding aggregate data by multiple keys
    }

    // Single-key join
    else {
      $field = key($keys);
      $joinField = $keys[$field];
      $index = array();
      foreach($data as &$doc) {
        $key = $doc[$joinField];
        unset($doc[$joinField]);
        $index[$key] = $doc;
      }
      foreach($this->getItems() as $item) {
        $id = $item->getData($field);
        if(isset($index[$id])) {
          $item->addData($index[$id]);
        } else if($defaults) {
          $item->addData($defaults);
        }
      }
    }
    return $this;
  }

  /**
   * Add field filter to collection.
   *
   * If $field is an array then each key => value is applied as a separate condition.
   *
   * If $field is '$or' or '$nor', each $condition is processed as part of the or/nor query.
   *
   * Filter by other collection value using the -> operator to separate the
   * field that references the other collection and the field in the other collection.
   * - ('bar_id->name', 'eq', 'Baz')
   *
   * If $_condition is not null and $condition is not an array, value will not be cast.
   *
   * If $condition is assoc array - one of the following structures is expected:
   * - array("from"=>$fromValue, "to"=>$toValue)
   * - array("eq|neq"=>$likeValue)
   * - array("like|nlike"=>$likeValue)
   * - array("null|notnull"=>TRUE)
   * - array("is"=>"NULL|NOT NULL")
   * - array("in|nin"=>$array)
   * - array("raw"=>$mixed) - No typecasting
   * - array("$__"=>$value) - Any mongo operator
   *
   * @param string $field
   * @param null|string|array $condition
   * @param null|string|array $_condition
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addFieldToFilter($field, $condition = NULL, $_condition = NULL)
  {
    switch (func_num_args()) {
      case 1:
        $query = $this->_getCondition($field); break;
      case 2:
        $query = $this->_getCondition($field, $condition); break;
      case 3: default:
        $query = $this->_getCondition($field, $condition, $_condition); break;
    }
    $this->_query->find($query);
    return $this;
  }

  /**
   * Adds a field to be preloaded after the collection is loaded.
   * If the collection is already loaded it will preload the referenced
   * collection immediately.
   *
   * Supports reference chains in field name using -> delimiter.
   *
   * @param string|array $field
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addFieldToPreload($field)
  {
    // Process arrays individually
    if(is_array($field)) {
      foreach($field as $_field) {
        $this->addFieldToPreload($_field);
      }
      return $this;
    }

    // If a field uses chaining
    if(strpos($field, '->'))
    {
      $parts = explode('->', $field);
      $collection = $this;
      while($_field = array_shift($parts)) {
        $collection = $collection->addFieldToPreload($_field)->getReferencedCollection($_field);
      }
    }
    
    // Otherwise
    else if( ! isset($this->_preloadFields[$field]))
    {
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
   * @param bool|int $include
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addFieldToResults($field, $include = 1)
  {
    $include = (int) $include;
    if( ! is_array($field)) {
      $field = array($field => $include);
    }
    else if($field == array_values($field)) {
      $field = array_fill_keys($field, $include);
    }

    foreach($field as $_field => $_include) {
      if( strpos($_field, '->') ) {
        list($fieldA, $fieldB) = explode('->', $_field, 2);
        $this->addFieldToResults($fieldA, 1);
        $this->getReferencedCollection($fieldA)->addFieldToResults($fieldB, $_include);
      }
      else {
        $this->_query->fields(array($_field => $_include));
      }
    }

    return $this;
  }

  /**
   * Check if the query already has explicit field includes specified (include and exclude cannot be mixed)
   *
   * @return bool
   */
  public function hasIncludedFields()
  {
    foreach($this->_query->get_option('fields') as $include) {
      if($include == 1) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Adds the collection to the resource cache after the collection is loaded
   *
   * @param boolean $now  Force the collection to be added now even if it is not loaded
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function addToCache($now = FALSE)
  {
    if( ($now || $this->isLoaded()) && ! isset($this->_addedToCache)) {
      $this->getResource()->addCollectionToCache($this);
      $this->_addedToCache = TRUE;
    }
    else {
      $this->_addToCacheAfterLoad = TRUE;
    }
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
    try {
      $resource = $this->getResource();
      if(strpos($field, '.')) {
        $parts = explode('.', $field);
        $field = array_pop($parts);
        while($_field = array_shift($parts)) {
          if(isset($resource->getFieldMapping($_field)->subtype)) {
            if( ! count($parts)) {
              $subtype = (string) $resource->getFieldMapping($_field)->subtype;
              return $resource->getPhpToMongoConverter()->$subtype((object)null, $value);
            }
            return $value;
          }
          $resource = $resource->getFieldResource($_field);
        }
      }
      return $resource->castToMongo($field, $value);
    }
    catch(Exception $e) {
      return $value;
    }
  }

  /**
   * Returns the referenced collection unloaded so that additional filters/parameters may be set.
   *
   * @param string $field
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function getReferencedCollection($field)
  {
    if(empty($this->_referencedCollections[$field]))
    {
      if( ! ($fieldModelName = $this->getResource()->getFieldModelName($field))) {
        throw new Exception("Could not get field model for $field");
      }
      $this->_referencedCollections[$field] = Mage::getSingleton($fieldModelName)->getCollection();
    }
    return $this->_referencedCollections[$field];
  }

  /**
   * Applies a filter to the given collection based on this collection's field values.
   *
   * @param string $field
   * @param Cm_Mongo_Model_Resource_Collection_Abstract $collection
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function applyReferencedCollectionFilter($field, $collection)
  {
    $ids = array();

    // Get all ids for the given field
    $fieldType = (string) $this->getResource()->getFieldType($field);
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
      throw new Mage_Core_Exception("Cannot get referenced collection for field '$field' of type '$fieldType'.");
    }

    // array_unique is slow, but required for compatibility with MongoId and possibly other id data types
    $ids = array_unique($ids);

    // Instantiate a collection filtered to the referenced objects using $in
    $collection->addFieldToFilter('_id', '$in', array_values($ids));
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
      $this->_resource = Mage::getResourceSingleton($this->_resourceModel);
    }
    return $this->_resource;
  }

  /**
   * Get the collection name for this collection resource model
   *
   * @param  string $entityName The entity name to get the collection name for (defaults to current entity)
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
      $query = clone $this->getQuery();
      $query->unset_option('sort');                // ignore sorting (not sure if this is necessary)
      $this->_totalRecords = $query->count(FALSE); // false ignores limit and skip
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
    if ( ( ! $this->_curPage && ! $this->_pageSize) && $this->isLoaded() || ! $noLoad) {
      return parent::getAllIds();
    }

    // Use fast method of getting ids, full documents not loaded
    $idsQuery = clone $this->_query;
    $idsQuery->set_option('fields', array('_id' => 1));
    $idsQuery->unset_option('skip');
    $idsQuery->unset_option('limit');
    $ids = array();
    foreach($idsQuery->cursor() as $document) {
      $ids[] = $document['_id'];
    }
    return $ids;
  }

  /**
   * Add cursor order
   *
   * @param   string|array $field
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
   * @param string|array $field
   * @param string $direction
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function unshiftOrder($field, $direction = self::SORT_ORDER_DESC)
  {
    return $this->_setOrder($field, $direction, true);
  }

  /**
   * Unset all applied sort orders
   *
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  public function resetOrder()
  {
    $this->_query->unset_option('sort');
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
    $this->resetData();

    if($printQuery || $logQuery) {
      $this->debugQuery($printQuery, $logQuery);
    }

    if (is_array($documents)) {
      foreach ($documents as $data) {
        $item = $this->getNewEmptyItem();
        $this->getResource()->hydrate($item, $data, TRUE);
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
    if ($this->_iterating)
    {
      throw new Exception('Cannot getData while iterating.');
    }

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
   * This method cannot be used with load(), getData() or the default iterator
   * _beforeLoad and _afterLoadData are not called
   *
   * @return null|Cm_Mongo_Model_Abstract
   */
  public function getNextItem()
  {
    if ( ! $this->_iterating) {
      if ($this->isLoaded() || $this->_data) {
        throw new Exception('Cannot use getNextItem after collection is loaded.');
      }
      $this->_renderLimit();
      $this->_renderFields();
      $this->_iterating = TRUE;
    }
    $data = $this->_query->getNext();
    if( ! $data) {
      $this->_iterating = FALSE;
      return NULL;
    }
    $item = $this->getNewEmptyItem();
    $this->getResource()->hydrate($item, $data, TRUE);
    return $item;
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
    $this->_iterating = FALSE;
    $this->_items = array();
    $this->_data = null;
    $this->_preloadFields = array();
    $this->_referencedCollections = array();
    $this->getResource()->removeCollectionFromCache($this);
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
   * Overridden to set correct id field name
   *
   * @return array
   */
  public function  toOptionArray()
  {
    return $this->_toOptionArray('_id', 'name');
  }

  /**
   * Overridden to set correct id field name
   *
   * @return array
   */
  public function  toOptionHash()
  {
    return $this->_toOptionHash('_id', 'name');
  }

  /**
   * Before load action
   */
  protected function _beforeLoad() { }

  /**
   * Process loaded collection data
   */
  protected function _afterLoadData() { }

  /**
   * Load references
   */
  protected function _loadReferences()
  {
    foreach($this->_preloadFields as $field => $true)
    {
      $collection = $this->getReferencedCollection($field);
      if( ! $collection->isLoaded()) {
        $this->applyReferencedCollectionFilter($field, $collection);
        $collection->addToCache(TRUE);
      }
    }
  }

  /**
   * After load action
   */
  protected function _afterLoad()
  {
    if(isset($this->_addToCacheAfterLoad)) {
      $this->addToCache();
    }
  }

  /**
   * Build query condition. See docs for addFieldToFilter.
   *
   * @param string|array $fieldName
   * @param integer|string|array $condition
   * @param mixed $_condition
   * @return array
   */
  protected function _getCondition($fieldName, $condition = NULL, $_condition = NULL)
  {
    // If $fieldName is an array it is assumed to be multiple queries as $fieldName => $condition
    if (is_array($fieldName)) {
      $query = array();
      foreach($fieldName as $_fieldName => $_condition) {
        $query = array_merge($query, $this->_getCondition($_fieldName, $_condition));
      }
    }

    // When using third argument, convert to two arguments
    else if ( func_num_args() == 3 ) {
      $query = $this->_getCondition($fieldName, array($condition => $_condition));
    }

    // Handle multi-field filters with field names like firstname|lastname using $or
    else if (strpos($fieldName, '|')) {
      $query = array();
      foreach(explode('|', $fieldName) as $_fieldName) {
        switch (func_num_args()) {
          case 2:
            $query[] = $this->_getCondition($_fieldName, $condition); break;
          case 3:
          default:
            $query[] = $this->_getCondition($_fieldName, $condition, $_condition); break;
        }
      }
      $query = array('$or' => $query);
    }

    // Handle cross-collection filters with field names like bar_id->name
    else if (strpos($fieldName, '->')) {
      list($reference,$referenceField) = explode('->', $fieldName, 2);
      $collection = Mage::getSingleton($this->getResource()->getFieldModelName($reference))->getCollection();
      switch (func_num_args()) {
        case 2:
          $collection->addFieldToFilter($referenceField, $condition); break;
        case 3:
        default:
          $collection->addFieldToFilter($referenceField, $condition, $_condition); break;
      }
      $query = array($reference => array('$in' => $collection->getAllIds(TRUE)));
    }

    // Process sub-queries of or and nor
    else if ($fieldName == 'or' || $fieldName == 'nor') {
      $query = array();
      foreach($condition as $_condition) {
        $query[] = $this->_getCondition($_condition);
      }
      $query = array('$or' => $query);
    }

    // Process sub-queries of $and operator
    else if ($fieldName == 'and') {
      $query = array();
      foreach($condition as $_condition) {
        $query[] = $this->_getCondition($_condition);
      }
      $query = array('$and' => $query);
    }

    // Process where (javascript) queries
    else if ($fieldName == 'where') {
      $query = array('$where' => $condition);
    }

    // Bypass all processing if field name is an operator
    else if (substr($fieldName,0,1) == '$') {
      $query = array($fieldName => $condition);
    }

    // Process special condition keys
    else if (is_array($condition)) {

      // If condition key is an operator, make no changes
      if (count($condition) == 1 && substr(key($condition),0,1) == '$') {
        $query = array($fieldName => $condition);
      }

      // Range queries
      elseif (isset($condition['from']) || isset($condition['to'])) {
        $query = array();
        if (isset($condition['from'])) {
          $query['$gte'] = $this->castFieldValue($fieldName, $condition['from']);
        }
        if (isset($condition['to'])) {
          $query['$lte'] = $this->castFieldValue($fieldName, $condition['to']);
        }
        $query = array($fieldName => $query);
      }

      // Multiple conditions
      elseif (count($condition) > 1) {
        $query = array();
        foreach($condition as $_condition => $value) {
          $_condition = $this->_getCondition($fieldName, array($_condition => $value));
          $query = array_merge($query, $_condition[$fieldName]);
        }
        $query = array($fieldName => $query);
      }

      // Equality
      elseif (array_key_exists('eq', $condition)) {
        // Nested data, assume exact match (should we bother to typecast?)
        if(strpos($fieldName,'.') !== false) {
          $query = array($fieldName => $condition['eq']);
        }
        // Search array for presence of a single value
        else if(
          ! is_array($condition['eq']) &&
          in_array($this->getResource()->getFieldType($fieldName), array('set','referenceSet','embeddedSet'))
        ) {
          $query = array($fieldName => $condition['eq']);
        }
        // Search for an exact match
        else {
          $query = array($fieldName => $this->castFieldValue($fieldName, $condition['eq']));
        }
      }
      elseif (array_key_exists('neq', $condition)) {
        // Search array for presence of a single value
        if( ! is_array($condition['neq']) && $this->getResource()->getFieldType($fieldName) == 'set') {
          $query = array($fieldName => array('$ne' => $condition['neq']));
        }
        // Search for an exact match
        else {
          $query = array($fieldName => array('$ne' => $this->castFieldValue($fieldName, $condition['neq'])));
        }
      }

      // Like (convert SQL like to regex)
      elseif (array_key_exists('like', $condition)) {
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
      elseif (array_key_exists('nlike', $condition)) {
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

      // Test null by type
      elseif (array_key_exists('notnull', $condition)) {
        $query = array($fieldName => array('$not' => array('$type' => Mongo_Database::TYPE_NULL)));
      }
      elseif (array_key_exists('null', $condition)) {
        $query = array($fieldName => array('$type' => Mongo_Database::TYPE_NULL));
      }
      elseif (array_key_exists('is', $condition)) {
        $query = strtoupper($condition['is']);
        if($query == 'NULL' || $query === NULL) {
          $query = array($fieldName => array('$type' => Mongo_Database::TYPE_NULL));
        } else if($query == 'NOT NULL') {
          $query = array($fieldName => array('$not' => array('$type' => Mongo_Database::TYPE_NULL)));
        }
      }

      // Array queries
      elseif (array_key_exists('in', $condition)) {
        $values = array();
        if ( ! empty($condition['in'])) {
          if (in_array($this->getResource()->getFieldType($fieldName), array('set','referenceSet','embeddedSet','enumSet'))) {
            $values = $this->castFieldValue($fieldName, $condition['in']);
          }
          else {
            foreach($condition['in'] as $value) {
              $values[] = $this->castFieldValue($fieldName, $value);
            }
          }
        }
        $query = array($fieldName => array('$in' => $values));
      }
      elseif (array_key_exists('nin', $condition)) {
        $values = array();
        if ( ! empty($condition['nin'])) {
          if (in_array($this->getResource()->getFieldType($fieldName), array('set','referenceSet','embeddedSet','enumSet'))) {
            $values = $this->castFieldValue($fieldName, $condition['nin']);
          }
          else {
            foreach($condition['nin'] as $value) {
              $values[] = $this->castFieldValue($fieldName, $value);
            }
          }
        }
        $query = array($fieldName => array('$nin' => $values));
      }

      // Bypass typecasting
      elseif (array_key_exists('raw', $condition)) {
        $query = array($fieldName => $condition['raw']);
      }

      // Assume an "equals" query
      else {
        $query = $this->_getCondition($fieldName, array('eq' => $condition));
      }
    }

    // Condition is scalar
    else {
      $query = $this->_getCondition($fieldName, array('eq' => $condition));
    }

    return $query;
  }

  /**
   * Add sort order to the end or to the beginning
   *
   * @param string|array $field
   * @param string $direction
   * @param bool $unshift
   * @return Cm_Mongo_Model_Resource_Collection_Abstract
   */
  protected function _setOrder($field, $direction, $unshift = false)
  {
    if(is_array($field)) {
      foreach($field as $key => $value) {
        if(is_int($key)) {
          $_field = $value;
          $_direction = $direction;
        }
        else {
          $_field = $key;
          $_direction = $value;
        }
        $this->_setOrder($_field, $_direction, $unshift);
      }
      return $this;
    }

    if( ! ctype_digit($direction)) {
      $direction = (strtoupper($direction) == self::SORT_ORDER_ASC) ? Mongo_Collection::ASC : Mongo_Collection::DESC;
    }

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
   * @return \Cm_Mongo_Model_Resource_Collection_Abstract
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
