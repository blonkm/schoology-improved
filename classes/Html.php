<?
class Html {

  // generate hidden input fields to regenerate url from form
  // @param $ignore fields to ignore, to prevent duplicates after form submit
  function getParamsAsHiddenInputs($ignore) {
    $ret = '';    
    foreach ($_GET as $key=>$value) {
      if (array_search($key, $ignore)===false) {
        $fvalue = filter_input(INPUT_GET, $key);
        $field = '<input type="hidden" name="@name" value="@value" />' . "\n";
        $field = str_replace('@name', $key, $field);
        $field = str_replace('@value', $fvalue, $field);
        $ret .= $field;                    
      }
    }
    return $ret;
  }

  // 'selected' for a selected input
  function selected($value, $param) {
    if ($value==$param) 
      return 'selected';
    else
      return '';
  }
}  