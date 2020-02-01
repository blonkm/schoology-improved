<?

class Filesystem {

  private $_errors = [];

  public function addError($message) {
    $this->_errors[] = $message;
  }

  public function getErrors() {
    return $this->_errors;
  }

  function removeBase($base, $url) {
    return str_replace($base, '', $url);
  }

  function deleteFiles($dir) {
    foreach (glob($dir . '/*') as $file) {
      if (is_dir($file))
        $this->deleteFiles($file);
      else
        $this->unlink($file);
      $relativePath = substr($file, strlen(getcwd()) + 1);
      $this->addError('deleted: ' . $relativePath);
    }
    $relativePath = substr($dir, strlen(getcwd()) + 1);
    $this->addError("deleted: " . $relativePath);
    rmdir($dir);
  }

  /**
   * Unlink a file, which handles symlinks.
   * @see https://github.com/luyadev/luya/blob/master/core/helpers/FileHelper.php
   * @param string $filename The file path to the file to delete.
   * @return boolean Whether the file has been removed or not.
   */
  function unlink($filename) {
    // try to force symlinks
    if (is_link($filename)) {
      $sym = @readlink($filename);
      if ($sym) {
        return is_writable($filename) && @unlink($filename);
      }
    }

    // try to use real path
    if (realpath($filename) && realpath($filename) !== $filename) {
      return is_writable($filename) && @unlink(realpath($filename));
    }

    // default unlink
    return is_writable($filename) && @unlink($filename);
  }

  /* create names for file system without special characters (replace all by '-') */

  function sanitize($s) {
    return preg_replace('/[^a-zA-Z0-9\-\._]/', '-', $s);
  }

  function read($file) {
    $ret = file_get_contents($file);
    if (!$ret) {
      throw new Exception("Can't load file: " . $file);
    }
    return $ret;
  }

  function toArray($file) {
    $ret = file($file);
    if (!$ret) {
      throw new Exception("Can't load file: " . $file);
    }
    return $ret;
  }

  /**
   * @param $file   a csv file, with a header row
   * @param delimiter  delimiter
   * @returns an array of arrays, with header names used as keys
   */
  function csvToObj($file, $delimiter = ",") {
    $lines = $this->toArray($file);
    $csv = array_map(function ($item) use ($delimiter) {
      return str_getcsv($item, $delimiter);
    }, $lines);
    array_walk($csv, function(&$a) use ($csv) {
      $a = array_combine($csv[0], $a);
    });
    array_shift($csv); # remove column header
    return $csv;
  }

  function mkdir($folder) {
    if (!is_dir($folder)) {
      mkdir($folder, 0755, true);
    }
  }

}

?>