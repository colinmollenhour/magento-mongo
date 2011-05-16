<?php

class Cm_Mongo_Model_Indexer {

  public function indexObjectTask(Varien_Object $jobData)
  {
    /* @var $model Cm_Mongo_Model_Abstract */
    if( ! ($model = $jobData->getJob()->getObjectToIndex())) {
      $model = Mage::getModel($jobData->getModel());
      $model->load($jobData->getObjectId());
      if( ! $model->getId()) {
        // TODO throw
      }
      // TODO index fields
    }
  }

}
