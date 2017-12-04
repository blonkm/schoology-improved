<?
class Uploader {
	private $_targetDirectory;
	private $_errors;
	
	function __construct($dir) {
		$this->_targetDirectory = $dir;
		$this->_errors = [];
	}

  /* add another error to the list */
  function addError($message) {       
      $this->_errors[] = $message;
  }
  public function getErrors() {
    return $this->_errors;
  }
  public function hasErrors() {
    return count($this->_errors) > 0;
  }
  
	public function upload($fieldName, $type) {	
		if (!isset($_FILES)) {
			$this->addError("no file uploaded");
			return '';
		}

		// file properties
		$fileName = $_FILES[$fieldName]["name"];
		$tempName = $_FILES[$fieldName]["tmp_name"];
		$fileType = $_FILES[$fieldName]['type'];
		$fileSize = $_FILES[$fieldName]["size"];

		// upload dir properties
		$targetFile = $this->_targetDirectory . '/' . basename($fileName);
		$fileExt = pathinfo($targetFile, PATHINFO_EXTENSION);
		$uploadOk = true;

		// Check if the file is a csv file
		$check = filesize($tempName);  

    if ($type=='csv') {
      $csv_mimetypes = array(
        'text/csv',
        'text/plain',
        'application/csv',
        'text/comma-separated-values',
        'application/excel',
        'application/vnd.ms-excel',
        'application/vnd.msexcel',
        'text/anytext',
        'application/octet-stream',
        'application/txt',
      );

      if (!in_array($fileType, $csv_mimetypes)) {
        $uploadOk = false;
        $this->addError("Sorry, file is of wrong type: " . $fileType);
      }
    }

		// Check if file already exists
		if (file_exists($targetFile)) {
      $this->addError("Sorry, file already exists.");
      $uploadOk = false;
		}

		// Check file size
		if ($fileSize > 500000) {
      $this->addError("Sorry, your file is too large.");
      $uploadOk = false;
		}

		// Check if $uploadOk is set to false by an error
		if (!$uploadOk) {
      $this->addError("Sorry, your file was not uploaded.");
    // if everything is ok, try to upload file
		} else {
		  if (move_uploaded_file($tempName, $targetFile)) {
			  // echo "The file ". basename($fileName). " has been uploaded.";
		  } else {
        $this->addError("Sorry, there was an error uploading your file.");
		  }
		}
    return $targetFile;  
	}
}
?>