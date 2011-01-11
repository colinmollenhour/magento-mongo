<?php

class Cm_Mongo_Getter
{
  
  /** @var array */
  protected $_parts;

  /**
   * Useful for the getter property of a grid.
   *
   * 'getter' => new Cm_Mongo_Getter('getFoo.getBar'),
   *
   * @param string $path
   */
  public static function create($path)
  {
    $getter = new self($path);
    return array($getter, 'fetch');
  }

  public function  __construct($path)
  {
    $this->_parts = explode('.',$path);
  }

  /**
   * Get a nested value from an object using the getter's path.
   *
   * @param Varien_Object $object
   * @return mixed
   */
  public function fetch(Varien_Object $object)
  {
    for($value = $object, $i = 0; $i < count($this->_parts); $i++)
    {
      $value = $value->{$this->_parts[$i]}();
    }
    return $value;
  }

}