<?

class FlxZipArchive extends ZipArchive {

  /**     Add a Dir with Files and Subdirs to the archive; 
   *       @param string $location Real Location  
   *       @param string $name Name in Archive
   *       @author Nicolas Heimann 
   *       @access private  
   * */
  public function addDir($location, $name) {
    $this->addEmptyDir($name);
    $this->addDirDo($location, $name);
  }

  /**     Add Files & Dirs to archive 
   *       @param string $location Real Location;  
   *       @param string $name Name in Archive
   *       @author Nicolas Heimann 
   *       @access private   
   * */
  private function addDirDo($location, $name) {
    $name .= '/';
    $location .= '/';
    // Read all Files in Dir
    $dir = opendir($location);
    while ($file = readdir($dir)) {
      if ($file == '.' || $file == '..')
        continue;
      // recursive
      $do = (filetype($location . $file) == 'dir') ? 'addDir' : 'addFile';
      $this->$do($location . $file, $name . $file);
    }
  }

}

?>
