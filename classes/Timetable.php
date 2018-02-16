<?
/**
  Timetable: school timetable
  each start of a lesson is read from a file, e.g.
 ".7:30AM
  8:15AM"
  @author Michiel van der Blonk (blonkm@gmail.com)
  @date 2017-11-20
*/

class Timetable {
  private $times;
  
  public function __construct() {
      $FS = new Filesystem;
        $this->times = $FS->toArray('./config/.timetable');
    }

  /**
   * @param $timestamp php (unix) timestamp
   * @returns number of seconds since midnight (aka the time minus the date)
   */
  public function secondsSinceMidnight($timestamp) {
    if (!is_numeric($timestamp)) {
      throw new Exception('timestamp should be number');
    }
    $day = strtotime(date('Y-m-d', $timestamp));
    $seconds = $timestamp - $day;
    return $seconds;
  }

  /**
   * @param $timestamp php (unix) timestamp
   * @returns find where time given fits in school timetable
   */
  public function lesson($timestamp) {
    $seconds = $this->secondsSinceMidnight($timestamp);
    $lessons = array_map(function($time) { return $this->secondsSinceMidnight(strtotime($time)); }, $this->times);
    $first = $lessons[0];
    $last = $lessons[count($lessons)-1];
    if ($seconds < $first || $seconds > $last) {
      throw new Exception ('time not in lesson timetable');
    }
    foreach ($lessons as $key=>$lesson) {
      if ($lesson > $seconds)
        return $key;
    }
    return count($lessons);
  }
}
?>