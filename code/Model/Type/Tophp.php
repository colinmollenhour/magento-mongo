<?php
/**
 * Common data type conversion from Mongo values to PHP values.
 */
class Cm_Mongo_Model_Type_Tophp
{

  public function any($mapping, $value)
  {
    return $value;
  }

  public function int($mapping, $value)
  {
    return (int) $value;
  }

  public function string($mapping, $value)
  {
    return (string) $value;
  }

  public function float($mapping, $value)
  {
    return (float) $value;
  }

  public function bool($mapping, $value)
  {
    return (bool) $value;
  }

  public function MongoId($mapping, $value)
  {
    if($value instanceof MongoId) {
      return $value;
    }
    if(is_string($value)) {
      return new MongoId($value);
    }
    return NULL;
  }
  
  public function MongoDate($mapping, $value)
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

  public function timestamp($mapping, $value)
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

  public function datestring($mapping, $value)
  {
    if(is_string($value)) {
      return $value;
    }
    else if($value instanceof Zend_Date) {
      return $value->toString(Varien_Date::DATE_INTERNAL_FORMAT);
    }
    else if(is_int($value)) {
      $date = new Zend_Date($value);
      return $value->toString(Varien_Date::DATE_INTERNAL_FORMAT);
    }
    else if($value instanceof MongoDate) {
      $date = new Zend_Date($value->sec);
      return $date->toString(Varien_Date::DATE_INTERNAL_FORMAT);
    }
    else {
      return (string) $value;
    }
  }

  public function set($mapping, $value)
  {
    if(is_string($value) && isset($mapping->split)) {
      $regex = ($mapping->split == 'newline' ? '/[ \t]*[\r\n]+[ \t]*/' : (string) $mapping->split);
      $value = preg_split($regex, trim($value), null, PREG_SPLIT_NO_EMPTY);
    } else if( ! is_array($value) || key($value) != 0) {
      $value = array_values((array) $value);
    }
    if($mapping->subtype) {
      $subtype = (string) $mapping->subtype;
      foreach($value as &$val) {
        $val = $this->$subtype($mapping, $val);
      }
    }
    return $value;
  }

  public function hash($mapping, $value)
  {
    return (array) $value;
  }

  public function enum($mapping, $value)
  {
    return isset($mapping->options->$value) ? (string) $value : NULL;
  }

  public function enumSet($mapping, $value)
  {
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

  public function reference($mapping, $value)
  {
    return $value;
  }

  public function referenceSet($mapping, $value)
  {
    return $value;
  }

  public function __call($name, $args)
  {
    return Mage::getSingleton($name)->toPHP($args[0], $args[1]);
  }

}
