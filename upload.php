<?
function import() {
  if (!isset($_FILES)) {
	echo 'nothing to upload';
    die();
  }

  // file properties
  $fileName = $_FILES["upload"]["name"];
  $tempName = $_FILES["upload"]["tmp_name"];
  $fileType = $_FILES['upload']['type'];
  $fileSize = $_FILES["upload"]["size"];

  // upload dir properties
  $target_dir = "uploads/";
  $target_file = $target_dir . basename($fileName);
  $fileExt = pathinfo($target_file, PATHINFO_EXTENSION);
  $uploadOk = true;

  // Check if the file is a csv file
  $check = filesize($tempName);  

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
    echo "Sorry, file is of wrong type: " . $fileType;
  }

  // Check if file already exists
  if (file_exists($target_file)) {
      echo "Sorry, file already exists.";
      $uploadOk = false;
  }

  // Check file size
  if ($fileSize > 500000) {
      echo "Sorry, your file is too large.";
      $uploadOk = false;
  }

  // Check if $uploadOk is set to 0 by an error
  if (!$uploadOk) {
      echo "Sorry, your file was not uploaded.";
  // if everything is ok, try to upload file
  } else {
      if (move_uploaded_file($tempName, $target_file)) {
          echo "The file ". basename($fileName). " has been uploaded.";
      } else {
          echo "Sorry, there was an error uploading your file.";
      }
  }
}
import();
?>
