<?

function redirect($url, $permanent = false) {
  if (headers_sent() === false) {
    header('Location: ' . $url, true, ($permanent === true) ? 301 : 302);
  } else {
    echo "Headers already sent in $filename on line $linenum\n" .
    "Cannot redirect, for now please click this <a " .
    "href=\"groups.php?action=courses\">link</a> instead\n";
  }
  exit();
}

redirect('./groups.php?action=courses', false);
