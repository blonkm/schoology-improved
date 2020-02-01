<?

/**
  Timer: measure or delay time
  @author Michiel van der Blonk (blonkm@gmail.com)
  @date 2017-11-20
 */
class Timer {

  private $_start;
  private $_elapsed;
  private $_finish;
  private $_useMicroSeconds;
  private $_throttleRate;

  /**
   * @param $useMicroSeconds extra precision for throttling (one millionth)
   * @param $delay when true, let the caller handle beginning of startTime
   */
  public function __construct($useMicroSeconds = false, $delay = false) {
    $this->_useMicroSeconds = $useMicroSeconds;
    if (!$delay) {
      $this->setStartTime();
    }
  }

  private function getTime() {
    $time = microtime();
    $time = explode(' ', $time);
    $time = (double) $time[1] + (double) $time[0];
    if ($this->_useMicroSeconds)
      return $time * pow(10, 6);
    else
      return $time;
  }

  // throttle at n per second
  public function setThrottleRate($n) {
    $this->_throttleRate = $n;
  }

  public function setStartTime() {
    $time = $this->getTime();
    $this->_start = $time;
    return $time;
  }

  public function getElapsedTime() {
    $this->_finish = $this->getTime();
    $this->_elapsed = round(($this->_finish - $this->_start), 2);

    return $this->_elapsed;
  }

  // delay execution so we don't go over n times per second
  // for e.g. API calls
  public function throttle() {
    if ($this->_throttleRate == 0)
      return;
    $million = pow(10.0, 6);
    $precision = $this->_useMicroSeconds ? $million : 1.0;
    $delayFunction = $this->_useMicroSeconds ? 'usleep' : 'sleep';
    $delay = $precision / $this->_throttleRate; // in (micro)seconds
    $timeLeft = $delay - $this->getElapsedTime();
    if ($timeLeft > 0) {
      $delayFunction($timeLeft);
    }
  }

}

?>