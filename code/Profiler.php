<?php

class Cm_Mongo_Profiler
{

  /**
   * Timers for code profiling
   *
   * @var array
   */
  static private $_timers = array();

  public static function reset($timerName)
  {
    self::$_timers[$timerName] = array(
    	'start'=>false,
    	'count'=>0,
    	'sum'=>0,
    	'realmem'=>0,
    	'emalloc'=>0,
    );
  }

  public static function resume($timerName)
  {
    if (empty(self::$_timers[$timerName])) {
      self::reset($timerName);
    }
    self::$_timers[$timerName]['start'] = microtime(true);
    self::$_timers[$timerName]['count'] ++;
  }

  public static function start($timerName)
  {
    self::resume($timerName);
  }

  public static function pause($timerName)
  {
    if (empty(self::$_timers[$timerName])) {
      self::reset($timerName);
    }
    if (false!==self::$_timers[$timerName]['start']) {
      self::$_timers[$timerName]['sum'] += microtime(true)-self::$_timers[$timerName]['start'];
      self::$_timers[$timerName]['start'] = false;
    }
  }

  public static function stop($timerName)
  {
    self::pause($timerName);
  }

  public static function fetch($timerName, $key='sum')
  {
    if (empty(self::$_timers[$timerName])) {
      return false;
    } elseif (empty($key)) {
      return self::$_timers[$timerName];
    }
    switch ($key) {
      case 'sum':
        $sum = self::$_timers[$timerName]['sum'];
        if (self::$_timers[$timerName]['start']!==false) {
          $sum += microtime(true)-self::$_timers[$timerName]['start'];
        }
        return $sum;

      case 'count':
        $count = self::$_timers[$timerName]['count'];
        return $count;

      default:
        if (!empty(self::$_timers[$timerName][$key])) {
          return self::$_timers[$timerName][$key];
        }
    }
    return false;
  }

  public static function getTimers()
  {
    return self::$_timers;
  }

}
