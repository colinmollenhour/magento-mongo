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
