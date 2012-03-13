<?php
/**
 * Generates callbacks to use for grid columns
 */
class Cm_Mongo_Getter
{
  
  /** @var array */
  protected $_parts;

  /** @var string */
  protected $_type;

  /**
   * Useful for the getter property of a grid.
   *
   * 'getter' => Cm_Mongo_Getter::factory('getFoo.getBar'),
   * 'getter' => Cm_Mongo_Getter::factory('getFoo.getUpdatedAt', 'timestamp'),
   *
   * @param string $path    Dot-delimited path to desired data
   * @param string $type    Optional data type to convert value to
   * @return array
   */
  public static function factory($path, $type = NULL)
  {
    $getter = new self($path, $type);
    return array($getter, $type === NULL ? 'fetch' : 'fetchAsType');
  }

  /**
   * Recommended to instead use static factory method.
   *
   * @see Cm_Mongo_Getter::factory()
   *
   * @param string $path    Dot-delimited path to desired data
   * @param string $type    Optional data type to convert value to
   */
  public function  __construct($path, $type = NULL)
  {
    $this->_parts = explode('.',$path);
    $this->_type = $type;
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

  /**
   * Fetch the value and convert the type using the tophp converter.
   *
   * @param Varien_Object $object
   * @return mixed
   */
  public function fetchAsType(Varien_Object $object)
  {
    $value = $this->fetch($object);
    return Mage::getSingleton('mongo/type_tophp')->{$this->_type}(NULL, $value);
  }

}
