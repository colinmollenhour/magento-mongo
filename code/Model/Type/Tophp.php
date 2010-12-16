<?php

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

  public function mongodate($mapping, $value)
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
  
  public function set($mapping, $value)
  {
    return array_values((array) $value);
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

  public function embedded($mapping, $value)
  {
    $model = Mage::getModel($mapping->model);
    $model->getResource()->hydrate($model, $value);
    return $model;
  }

  public function embeddedSet($mapping, $value)
  {
    $set = new Varien_Data_Collection();
    //$set->setItemObjectClass($mapping->model);
    foreach($value as $itemData)
    {
      $model = Mage::getModel($mapping->model);
      $model->getResource()->hydrate($model, $itemData);
      $model->setOrigData();
      $set->addItem($model);
    }
    return $set;
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
