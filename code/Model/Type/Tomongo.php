<?php

class Cm_Mongo_Model_Type_Tomongo
{

  public function any($mapping, $value, $forUpdate = FALSE)
  {
    return $value;
  }

  public function int($mapping, $value, $forUpdate = FALSE)
  {
    return (int) $value;
  }

  public function string($mapping, $value, $forUpdate = FALSE)
  {
    return (string) $value;
  }

  public function float($mapping, $value, $forUpdate = FALSE)
  {
    return (float) $value;
  }

  public function bool($mapping, $value, $forUpdate = FALSE)
  {
    return (bool) $value;
  }

  public function mongodate($mapping, $value, $forUpdate = FALSE)
  {
    if($value instanceof MongoDate) {
      return $value;
    }
    else if(is_array($value) && isset($value['sec'])) {
      return new MongoDate($value['sec'], isset($value['usec']) ? $value['usec'] : 0);
    }

    $value = $this->timestamp($mapping, $value);
    if($value === NULL) {
      return NULL;
    }
    return new MongoDate((int)$value);
  }

  public function timestamp($mapping, $value, $forUpdate = FALSE)
  {
    if($value instanceof MongoDate) {
      return $value->sec;
    }
    else if($value === NULL) {
      return NULL;
    }
    else if(is_int($value) || is_float($value)) {
      return (int) $value;
    }
    else if($value instanceof Zend_Date) {
      return $value->getTimestamp();
    }
    else if(is_array($value) && isset($value['sec'])) {
      return $value['sec'];
    }
    else if( ! strlen($value)) {
      return NULL;
    }
    else if(ctype_digit($value)) {
      return intval($value);
    }
    else if(($time = strtotime($value)) !== false) {
      return $time;
    }
    return NULL;
  }

  public function set($mapping, $value, $forUpdate = FALSE)
  {
    return array_values((array) $value);
  }

  public function hash($mapping, $value, $forUpdate = FALSE)
  {
    return empty($value) ? new ArrayObject : (array) $value;
  }

  public function enum($mapping, $value, $forUpdate = FALSE)
  {
    return isset($mapping->options->$value) ? (string) $value : NULL;
  }

  public function enumSet($mapping, $value, $forUpdate = FALSE)
  {
    if($value instanceof Mage_Core_Model_Config_Element) {
      $value = array_keys($value->asCanonicalArray());
    }
    
    if( ! is_array($value) || ! count($value)) {
      return array();
    }
    $value = array_unique($value);
    $rejects = array();
    foreach($value as $val) {
      if( ! $this->enum($mapping, $val)) {
        $rejects[] = $val;
      }
    }
    if($rejects) {
      return array_diff($value, $rejects);
    }
    return $value;
  }

  public function embedded($mapping, $value, $forUpdate = FALSE)
  {
    if( ! is_object($value)) {
      return NULL;
    }
    $data = $value->getResource()->dehydrate($value, $forUpdate);
    return $data;
  }

  public function embeddedSet($mapping, $value, $forUpdate = FALSE)
  {
    $data = array();
    if($value instanceof Varien_Data_Collection) {
      $items = $value->getItems();
    }
    else {
      $items = (array) $value;
    }
    foreach($items as $item) {
      if($item instanceof Cm_Mongo_Model_Abstract) {
        $data[] = $item->getResource()->dehydrate($item, $forUpdate);
      }
      else if($item instanceof Varien_Object) {
        $data[] = $item->getData();
      }
      else {
        $data[] = (array) $item;
      }
    }
    return $data;
  }

  public function reference($mapping, $value)
  {
    return ($value instanceof Varien_Object ? $value->getId() : $value);
  }

  public function referenceSet($mapping, $value)
  {
    $ids = array();
    foreach($value as $item) {
      $ids[] = is_object($item) ? $item->getId() : $item;
    }
    return $ids;
  }

  public function __call($name, $args)
  {
    if( ! isset($args[2])) {
      $args[2] = FALSE;
    }
    return Mage::getSingleton($name)->toMongo($args[0], $args[1], $args[2]);
  }

}
