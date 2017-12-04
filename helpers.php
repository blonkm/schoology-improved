<?
/*
 * various helper functions
 */
 
/* 
* specific for EPI, Aruba: convert short id to long id T15-0001
* @param shortId an ID like T15001 to be converted
* returns a long id with '-' and '0' like T15-0001
*/
if (!function_exists('formatId')) {
  function formatId($shortId) {
      return substr($shortId,0,3) . "-0" . substr($shortId, -3);
  }
}

// setup debug
if (!function_exists('debug')) {
  function debug($on = true) {
    date_default_timezone_set('America/Aruba');
    if ($on) {
      error_reporting(E_ALL);
      ini_set('display_errors', 1);
    }
    else {
        error_reporting(E_ERROR | E_WARNING | E_PARSE);
        ini_set('display_errors', 0);
    }
  }
}

// print a variable in html using PRE tags
if (!function_exists('dump')) {
  function dump($s) {
      echo "<pre>";
      var_dump($s);
      echo "</pre>";
  }
}

// dump and die: print variable and stop script (die)
if (!function_exists('dd')) {
  function dd($s) {
      dump($s);
      die();
  }
}

// return 'active' for active menu name
if (!function_exists('isMenuActive')) {
  function isMenuActive($action, $name) {
    if ($action==$name) 
      return 'active';
    else
      return '';
  }
}

// single quotes around a string
if (!function_exists('sq')) {
  function sq($s) {
    return "'" . $s . "'";
  }
}

?>
