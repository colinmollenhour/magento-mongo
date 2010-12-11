<?php

class Cm_Mongo_Model_Type_Tomongo
{

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

  public function collection($mapping, $value)
  {
    return array_values((array) $value);
  }

  public function hash($mapping, $value)
  {
    return (object) $value;
  }

  public function enum($mapping, $value)
  {
    return isset($mapping->options->$value) ? $value : NULL;
  }

  public function embedded($mapping, $value)
  {
    if( ! is_object($value)) {
      return NULL;
    }
    $data = $value->getResource()->dehydrate($value, $forUpdate);
    return $data;
  }

  public function embeddedSet($mapping, $value)
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
        $data[] = $item->getResource()->dehydrate($item);
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
    return is_object($value) ? $value->getId() : $value;
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
