Cm_Mongo
========

This Magento extension is primarily two things:

* **An abstraction layer for MongoDb.** Like the MySQL abstraction layer included in Magento, it provides
classes that extend `Mage_Core_Model_Abstract` and `Varien_Data_Collection`. It also includes support for
.js upgrade scripts, an indexer to automatically index fields in other collections, and a schema
defined by XML files.

* **An atomic job queue.** The job queue can be run by the Magento cron or by a separate script and supports
future execution dates, automatic retry intervals, disabling jobs by name or group name, and priorities.

See [mongo.xml.sample](code/etc/mongo.xml.sample) and [Job.php](code/Model/Job.php) for more information.

Unit Testing
------------

Cm_Mongo can be used with [EcomDev_PhpUnit](https://github.com/IvanChepurnyi/EcomDev_PHPUnit),
but needs to override one of it's classes for fixtures with mongo collections to work. Add a module
dependency on EcomDev_PhpUnit to Cm_Mongo.xml when using with EcomDev_PhpUnit.


License
-------

Please see the terms defined in LICENSE.txt
