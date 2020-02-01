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
    $lessons = $FS->csvToObj('./config/.timetable', "\t");
    $this->times = array();
    foreach ($lessons as $key => $lesson) {
      $t = new stdClass;
      $t->startTime = $this->timeToSeconds(strtotime($lesson["startTime"]));
      $t->endTime = $this->timeToSeconds(strtotime($lesson["endTime"]));
      $t->hour = $lesson["hour"];
      $this->times[] = $t;
    }
  }

  /**
   * @param $timestamp php (unix) timestamp
   * @returns number of seconds since midnight (aka the time minus the date)
   */
  public function timeToSeconds($timestamp) {
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
    $seconds = $this->timeToSeconds($timestamp);
    $lessons = $this->times;
    $first = $lessons[0]->startTime;
    $last = $lessons[count($lessons) - 1]->endTime;
    if ($seconds < $first || $seconds > $last) {
      throw new Exception('time not in lesson timetable');
    }
    foreach ($lessons as $key => $lesson) {
      if ($seconds > $lesson->startTime && $seconds < $lesson->endTime)
        return $lesson->hour;
    }
    return count($lessons);
  }

}

?>