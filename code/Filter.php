<?php
/**
 * Generates callbacks for filtering a collection on conditions from another collection
 */
class Cm_Mongo_Filter
{
  /** @var string */
  protected $_resource;

  /** @var string */
  protected $_field;

  /** @var array */
  protected $_filters;

  /**
   * Useful for applying column filter conditions to a collection from another collection.
   *
   * 'filter_condition_callback' => Cm_Mongo_Filter::factory(
   *   'foo/bar_collection',
   *   'org_id',
   *   array(
   *     'status' => array('$ne' => 'inactive'),
   *   )
   * ),
   * 'filter_index' => 'name',
   *
   * @param string $resource    Resource name or collection instance
   * @param string $field       Field name to apply condition to
   * @param string $filters     Optional filters to apply before conditions
   * @return array
   */
  public static function factory($resource, $field, $filters = NULL)
  {
    $filter = new self($resource, $field, $filters);
    return array($filter, 'apply');
  }

  /**
   * Recommended to instead use static factory method.
   *
   * @see Cm_Mongo_Filter::factory()
   *
   * @param string $resource    Resource name or collection instance
   * @param string $field       Field name to apply condition to
   * @param string $filters     Optional filters to apply before conditions
   */
  public function  __construct($resource, $field, $filters = NULL)
  {
    $this->_resource = $resource;
    $this->_field    = $field;
    $this->_filters  = $filters;
  }

  /**
   * Apply the condition to the given collection by way of the resource specified on instantiation
   *
   * @param \Cm_Mongo_Model_Resource_Collection_Abstract $collection
   * @param \Mage_Adminhtml_Block_Widget_Grid_Column $column
   */
  public function apply(Cm_Mongo_Model_Resource_Collection_Abstract $collection, Mage_Adminhtml_Block_Widget_Grid_Column $column)
  {
    if(is_string($this->_resource)) {
      $filterCollection = Mage::getResourceModel($this->_resource);
    }
    else {
      $filterCollection = $this->_resource;
    }

    var_dump($filterCollection);
    if($this->_filters) {
      $filterCollection->addFieldToFilter($this->_filters);
    }

    $field = $column->getFilterIndex() ? $column->getFilterIndex() : $column->getIndex();
    $cond = $column->getFilter()->getCondition();
    if ($field && isset($cond)) {
      $filterCollection->addFieldToFilter($field , $cond);
    }
    $collection->addFieldToFilter($this->_field, '$in', $filterCollection->getAllIds(TRUE));
  }

}
