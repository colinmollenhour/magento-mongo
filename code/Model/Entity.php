<?php
/**
 * Piggyback EAV but use Mongo for storage
 *
 * NOT COMPLETE AND NOT WORKING 
 */
abstract class Cm_Mongo_Model_Entity
    extends Mage_Eav_Model_Entity_Abstract
{

    const DEFAULT_ENTITY_ID_FIELD = '_id';
    
    /**
     * Resource initialization
     */
    protected function _construct()
    {

    }

    /**
     * Get entity id field name in entity table
     *
     * @return string
     */
    public function getEntityIdField()
    {
        if (empty($this->_entityIdField)) {
            $this->_entityIdField = $this->getEntityType()->getEntityIdField();
            if (empty($this->_entityIdField)) {
                $this->_entityIdField = self::DEFAULT_ENTITY_ID_FIELD;
            }
        }
        return $this->_entityIdField;
    }

    /**
     * Check whether the attribute is a real field in entity table
     *
     * @see Mage_Eav_Model_Entity_Abstract::getAttribute for $attribute format
     * @param integer|string|Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @return unknown
     */
    public function isAttributeStatic($attribute)
    {
      return TRUE;
    }

    /**
     * Enter description here...
     *
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param Varien_Object $object
     * @return boolean
     */
    public function checkAttributeUniqueValue(Mage_Eav_Model_Entity_Attribute_Abstract $attribute, $object)
    {
        $value = $object->getData($attribute->getAttributeCode());
        if ($attribute->getBackend()->getType() == 'datetime'){
            $date = new Zend_Date($value);
            $value = $date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT);
        }

        $data = $this->_getWriteAdapter()
            ->getCollection($this->getEntityTable())
            ->findOne(array(
                $attribute->getAttributeCode() => $value
            ), array($this->getEntityIdField() => 1));

        if ($object->getId()) {
            if ($data) {
                return $data[$this->getEntityIdField()] == $object->getId();
            }
            return true;
        }
        else {
            return ! $data;
        }
    }

    /**
     * Load entity's attributes into the object
     *
     * @param   Varien_Object $object
     * @param   integer $entityId
     * @param   array|null $attributes
     * @return  Mage_Eav_Model_Entity_Abstract
     */
    public function load($object, $entityId, $attributes=array())
    {
        /**
         * Load object base row data
         */
        if (empty($attributes)) {
            $this->loadAllAttributes($object);
            $attributes = array_keys($this->_attributesByCode);
        } else {
            foreach ($attributes as $attrCode) {
                $this->getAttribute($attrCode);
            }
        }

        array_fill_keys($attributes, 1);

        $row = $this->_getReadAdapter()
            ->getCollection($this->getEntityTable())
            ->findOne(array(
                $this->getEntityIdField() => $entityId
            ), $attributes);

        if (is_array($row)) {
            $object->addData($row);
            $object->_isNewObject(FALSE);
        }

        $object->setOrigData();
        Varien_Profiler::start('__MONGO_EAV_LOAD_MODEL_AFTER_LOAD__');
        $this->_afterLoad($object);
        Varien_Profiler::stop('__MONGO_EAV_LOAD_MODEL_AFTER_LOAD__');

        return $this;
    }

    /**
     * Save entity's attributes into the object's resource
     *
     * @param   Varien_Object $object
     * @return  Mage_Eav_Model_Entity_Abstract
     */
    public function save(Varien_Object $object)
    {
        if ($object->isDeleted()) {
            return $this->delete($object);
        }

        if (!$this->isPartialSave()) {
            $this->loadAllAttributes($object);
        }

        if (!$object->getEntityTypeId()) {
            $object->setEntityTypeId($this->getTypeId());
        }

        $this->_beforeSave($object);
        $this->_processSaveData($this->_collectSaveData($object));
        $this->_afterSave($object);

        return $this;
    }

    /**
     * Prepare entity object data for save
     *
     * result array structure:
     * array (
     *  'newObject', 'entityRow', 'insert', 'update', 'delete'
     * )
     *
     * @param   Varien_Object $newObject
     * @return  array
     */
    protected function _collectSaveData($newObject)
    {
        $newData   = $newObject->getData();
        $entityId  = $newObject->getData($this->getEntityIdField());

        // define result data
        $entityRow  = array();
        $insert     = array();
        $update     = array();
        $delete     = array();

        if (!empty($entityId)) {
            $origData = $newObject->getOrigData();
            /**
             * get current data in db for this entity if original data is empty
             */
            if (empty($origData) && $newObject->isNewObject() === FALSE) {
                $origData = $this->_getOrigObject($newObject)->getOrigData();
            }

            /**
             * drop attributes that are unknown in new data
             * not needed after introduction of partial entity loading
             */
            if($origData) {
                foreach ($origData as $k => $v) {
                    if (!array_key_exists($k, $newData)) {
                        unset($origData[$k]);
                    }
                }
            }
        } else {
            $origData = array();
        }

        $attributeCodes = array_keys($this->_attributesByCode);

        foreach ($newData as $k => $v) {
            /**
             * Check attribute information
             */
            if (is_numeric($k) || is_array($v)) {
                continue;
            }
            /**
             * Check if data key is presented in static fields or attribute codes
             */
            if (!in_array($k, $attributeCodes)) {
                continue;
            }

            $attribute = $this->getAttribute($k);
            if (empty($attribute)) {
                continue;
            }

            $attrCode = $attribute->getAttributeCode();

            /**
             * Check comparability for attribute value
             */
            if (array_key_exists($k, $origData)) {
                if ($this->_isAttributeValueEmpty($attribute, $v)) {
                    $delete[$attrCode] = 1;
                } else if ($v !== $origData[$k]) {
                    $update[$attrCode] = $v;
                }
            } else if (!$this->_isAttributeValueEmpty($attribute, $v)) {
                $insert[$attrCode] = $v;
            }
        }

        $result = compact('newObject', 'insert', 'update', 'delete');
        return $result;
    }

    /**
     * Save object collected data
     *
     * @param   array $saveData array('newObject', 'insert', 'update', 'delete')
     * @return  Mage_Eav_Model_Entity_Abstract
     */
    protected function _processSaveData($saveData)
    {
        $object = NULL; // ?
        $newObject = $saveData['new_Object'];
        $insert = $saveData['insert'];
        $update = $saveData['update'];
        $delete = $saveData['delete'];
        $entityIdField  = $this->getEntityIdField();
        $entityId       = $newObject->getId();
        $condition      = array($entityIdField => $entityId);

        // Upsert when status is unknown
        if ($object->isNewObject() === NULL) {
          $this->_getWriteAdapter()->getCollection($this->getEntityTable())
            ->update($condition, $insert+$update, array('upsert' => TRUE, 'multiple' => FALSE, 'safe' => TRUE));
        }
        
        // Insert new objects
        else if($object->isNewObject()) {
          if($update) {
            $this->_getWriteAdapter()->getCollection($this->getEntityTable())
              ->update($insert, $update, array('upsert' => TRUE, 'multiple' => FALSE, 'safe' => TRUE));
          }
          else {
            $this->_getWriteAdapter()->getCollection($this->getEntityTable())
              ->insert($insert, array('safe' => TRUE));
          }
        }

        // Update existing objects
        else {
            $this->_getWriteAdapter()->getCollection($this->getEntityTable())
              ->update($condition, $insert+$update, array('multiple' => FALSE, 'safe' => TRUE));
        }

        /**
         * insert attribute values
         */
        if (!empty($insert)) {
            foreach ($insert as $attrCode => $value) {
                $attribute = $this->getAttribute($attrCode);
                $this->_insertAttribute($newObject, $attribute, $value);
            }
        }

        /**
         * update attribute values
         */
        if (!empty($update)) {
            foreach ($update as $attrCode => $value) {
                $attribute = $this->getAttribute($attrCode);
                //$this->_updateAttribute($newObject, $attribute, $value);
            }
        }

        /**
         * delete empty attribute values
         */
        if (!empty($delete)) {
            foreach ($delete as $attrCode => $value) {
                //$this->_deleteAttributes($newObject, $attrCode);
            }
        }

        $this->_processAttributeValues();

        return $this;
    }

    /**
     * Set entity attribute value
     *
     * Collect for mass save
     *
     * @param Mage_Core_Model_Abstract $object
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @param mixed $value
     * @return Mage_Eav_Model_Entity_Abstract
     */
    protected function _setAttribute($object, $attribute, $value)
    {
        $this->_attributeValuesToSave['$set'][$attribute->getAttributeCode()] = $this->_prepareValueForSave($value, $attribute);

        return $this;
    }

    /**
     * Prepare value for save
     *
     * @param mixed $value
     * @param Mage_Eav_Model_Entity_Attribute_Abstract $attribute
     * @return mixed
     */
    protected function _prepareValueForSave($value, Mage_Eav_Model_Entity_Attribute_Abstract $attribute)
    {
        if ($attribute->getBackendType() == 'decimal') {
            return Mage::app()->getLocale()->getNumber($value);
        }
        return $value;
    }

    /**
     * Save attribute
     *
     * @param Varien_Object $object
     * @param string $attributeCode
     * @return Mage_Eav_Model_Entity_Abstract
     */
    public function saveAttribute(Varien_Object $object, $attributeCode)
    {
        $attribute = $this->getAttribute($attributeCode);
        $backend = $attribute->getBackend();
        $entity = $attribute->getEntity();
        $entityIdField = $entity->getEntityIdField();

        $newValue = $object->getData($attributeCode);
        if ($attribute->isValueEmpty($newValue)) {
            $newValue = null;
        }

        $condition = array($object->getIdFieldName() => $object->getId());

        if ( ! is_null($newValue)) {
            $operation = array('$set' => array($attributeCode => $newValue));
        } else {
            $operation = array('$unset' => array($attributeCode => 1));
        }
        $this->_getWriteAdapter()->getCollection($this->getEntityTable())
          ->update($condition, $operation, array('multiple' => FALSE, 'safe' => TRUE));

        return $this;
    }

  /**
   * Delete entity using current object's data
   *
   * @param $object
   * @return Mage_Eav_Model_Entity_Abstract
   */
    public function delete($object)
    {
        if (is_numeric($object)) {
            $id = (int)$object;
        } elseif ($object instanceof Varien_Object) {
            $id = (int)$object->getId();
        }

        $this->_beforeDelete($object);

        $this->_getWriteAdapter()->getCollection($this->getEntityTable())->delete(array($this->getEntityIdField() => $id));

        $this->_afterDelete($object);
        return $this;
    }

}
