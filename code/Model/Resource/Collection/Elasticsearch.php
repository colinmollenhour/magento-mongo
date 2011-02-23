<?php

class Cm_Mongo_Model_Resource_Collection_Elasticsearch extends Cm_Mongo_Model_Resource_Collection_Abstract
{

  protected $_search;

  protected $_useSearch = FALSE;

  /**
   * Overload to perform initialization
   */
  public function _construct()
  {
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
   * @return Cm_Mongo_Model_Resource_Collection_Elasticsearch
   */
  public function addFieldToFilter($field, $condition=null, $_condition=null)
  {
    if($_condition !== NULL) {
      $condition = array($condition => $_condition);
    }
    $this->addFilter($field, $condition);
    return $this;
  }

  /**
   * Get the mongo collection instance which is used as a query object
   *
   * @return Mongo_Collection
   */
  public function getSearch()
  {
    if( ! $this->_search) {
      $this->_search = $this->getSearchConnection()->getQuery();
      $this->_useSearch = TRUE;
    }
    return $this->_search;
  }

  /**
   * Get collection size (ignoring limit and skip)
   *
   * @return int
   */
  public function getSize()
  {
    if (is_null($this->_totalRecords)) {
      $this->_renderQuery();
      if( ! $this->_useSearch) {
        return parent::getSize();
      }
      $this->_totalRecords = $this->getSearch()->count(FALSE); // false ignores limit and skip
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

    $this->_renderQuery();
    if( ! $this->_useSearch) {
      return parent::getAllIds($noLoad);
    }
    $result = $this->_search->blah(); // @TODO
    foreach($result as $key => $document) {
      $ids[] = $document['_id'];
    }
    return $ids;
  }

  /**
   * Add cursor order
   *
   * @param   string $field
   * @param   string $direction
   * @return  Cm_Mongo_Model_Resource_Collection_Elasticsearch
   */
  public function setOrder($field, $direction = self::SORT_ORDER_DESC)
  {
    $this->_orders[$field] = $direction;
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
      $this->_renderQuery();
      if($this->_useSearch) {
        $ids = $this->getAllIds();
        if($ids) {
          $this->_renderFields();
          $this->_data = $this->_query->find(array('_id' => array('$in' => $ids)))->as_array(FALSE);
        } else {
          $this->_data = array();
        }
      } else {
        $this->_data = $this->_query->as_array(FALSE);
      }
      $this->_afterLoadData();
    }
    return $this->_data;
  }

  /**
   * Reset collection to fresh state.
   *
   * @return Cm_Mongo_Model_Resource_Collection_Elasticsearch
   */
  public function reset()
  {
    parent::reset();
    $this->_useSearch = $this->_isFiltersRendered = FALSE;
    $this->_search = NULL;
    $this->_filters = $this->_orders = array();
    return $this;
  }

  /**
   * Build query condition. See docs for addFieldToFilter.
   *
   * @param string $fieldName
   * @param integer|string|array $condition
   * @param mixed $_condition
   * @return array
   */
  protected function _getSearchCondition($fieldName, $condition = NULL, $_condition = NULL)
  {
    // If $fieldName is an array it is assumed to be multiple queries as $fieldName => $condition
    if (is_array($fieldName)) {
      $query = array();
      foreach($fieldName as $_fieldName => $_condition) {
        $query = array_merge($query, $this->_getCondition($_fieldName, $_condition));
      }
    }

    // Handle cross-collection filters with field names like bar_id:name
    else if (strpos($fieldName, '->')) {
      list($reference,$referenceField) = explode('->', $fieldName, 2);
      $collection = Mage::getSingleton($this->getResource()->getFieldModelName($reference))->getCollection();
      $collection->addFieldToFilter($referenceField, $condition, $_condition);
      $query = array($reference => array('$in' => $collection->getAllIds(TRUE)));
    }

    // When using third argument, convert to two arguments
    else if ( $_condition !== NULL) {
      $query = $this->_getCondition($fieldName, array($condition => $_condition));
    }

    // Process sub-queries of or and nor
    elseif ($fieldName == '$or' || $fieldName == '$nor') {
      $query = array();
      foreach($condition as $_fieldName => $_condition) {
        $query = array_merge($query, $this->_getCondition($_fieldName, $_condition));
      }
      $query = array($fieldName => $query);
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
      elseif (isset($condition['eq'])) {
        // Search array for presence of a single value
        if( ! is_array($condition['eq']) && $this->getResource()->getFieldType($fieldName) == 'set') {
          $query = array($fieldName => $condition['eq']);
        }
        // Search for an exact match
        else {
          $query = array($fieldName => $this->castFieldValue($fieldName, $condition['eq']));
        }
      }
      elseif (isset($condition['neq'])) {
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

      // Test null by type
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

      // Array queries
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

      // Bypass typecasting
      elseif (isset($condition['raw'])) {
        $query = array($fieldName => $condition);
      }

      // Assume an "equals" query
      else {
        $query = $this->_getCondition($fieldName, 'eq', $condition);
      }
    }

    // Condition is scalar
    else {
      $query = $this->_getCondition($fieldName, 'eq', $condition);
    }

    return $query;
  }

  /**
   * Render the query filters, order and limits. Determines first if search should be used.
   *
   * @param string $field
   * @param string $direction
   * @param bool $unshift
   * @return Cm_Mongo_Model_Resource_Collection_Elasticsearch
   */
  protected function _renderQuery()
  {
    if($this->_isFiltersRendered) {
      return $this;
    }

    // Determine if search should be used
    if( ! $this->_useSearch) {

    }

    // Render the query using the appropriate database
    if($this->_useSearch) {
      // Render query as elasticsearch
    }
    else {
      // Render query as mongo query
      foreach($this->_filters as $field => $condition) {
        $this->_query->find($this->_getCondition($field, $condition));
      }
      foreach($this->_orders as $field => $direction) {
        $this->_query->sort($field, $direction);
      }
      $this->_renderLimit()->_renderFields();
    }
    $this->_isFiltersRendered = TRUE;
  }

}
