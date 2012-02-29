<?php

class Cm_Mongo_Profiler
{

  /**
   * Timers for code profiling
   *
   * @var array
   */
  static private $_timers = array();

  /**
   * @static
   * @param string $timerName
   */
  public static function reset($timerName)
  {
    self::$_timers[$timerName] = array(
    	'start'=>false,
    	'count'=>0,
    	'sum'=>0,
    );
  }

  /**
   * @static
   * @param string $timerName
   */
  public static function resume($timerName)
  {
    if (empty(self::$_timers[$timerName])) {
      self::reset($timerName);
    }
    self::$_timers[$timerName]['start'] = microtime(true);
    self::$_timers[$timerName]['count'] ++;
  }

  /**
   * @static
   * @param string $timerName
   */
  public static function start($timerName)
  {
    self::resume($timerName);
  }

  /**
   * @static
   * @param string $timerName
   */
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

  /**
   * @static
   * @param string $timerName
   */
  public static function stop($timerName)
  {
    self::pause($timerName);
  }

  /**
   * @static
   * @param string $timerName
   * @param string $key
   * @return bool|mixed
   */
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

  /**
   * @static
   * @return array
   */
  public static function getTimers()
  {
    return self::$_timers;
  }

  /**
   * Print (or get) all timers
   *
   * @static
   * @param bool $return
   * @return string
   */
  public static function debug($return = false)
  {
    if($return) {
      ob_start();
    }
    foreach(self::$_timers as $name => $timer) {
      printf("%d (%1.4fms): %s\n", $timer['count'], $timer['sum'], $name);
    }
    if($return) {
      return ob_get_clean();
    }
    return NULL;
  }

}
