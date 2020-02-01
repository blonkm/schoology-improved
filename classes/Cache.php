<?

// just for retrieval from database:
class Resource {

  public $url;
  public $response;
  public $expires;

  public function __construct($url = '', $response = '', $expires = '') {
    if ($url != '')
      $this->url = $url;
    if ($response != '')
      $this->response = $this->escape($response);
    if ($expires != '')
      $this->expires = $expires;
  }

  // @param $response the object from the API
  // @returns a sql-ready escaped json string
  public function escape($response) {
    $jsonResponse = json_encode($response);
    $escapedResponse = addcslashes($jsonResponse, "'");
    $escapedResponse = str_replace("'", "''", $jsonResponse);
    return $escapedResponse;
  }

  public function getExpiryTime() {
    return strtotime($this->expires);
  }

  public function getInsertStatement() {
    $expiryTime = $this->getExpiryTime();
    $sql = "INSERT OR REPLACE INTO resources (url, response, expires) VALUES ('{$this->url}', '{$this->response}', '$expiryTime');";
    return $sql;
  }

}

/*
 * save json results from api in a database
 * so we can get cached results
 */

class Cache {

  private $db;
  private $active;

  public function __construct() {
    try {
      if (!extension_loaded('sqlite3')) {
        dump('Sqlite PHP extension not available');
        die();
      }
      $this->db = new PDO('sqlite:api.sqlite');
      $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $this->db->exec("CREATE TABLE IF NOT EXISTS resources (url TEXT PRIMARY KEY, response TEXT, expires TEXT)");
    } catch (PDOException $e) {
      error_log('Exception : ' . $e->getMessage());
    }
    $this->enable();
  }

  public function disable() {
    $this->active = false;
  }

  public function enable() {
    $this->active = true;
  }

  public function isActive() {
    return $this->active;
  }

  /*
   * @param url the api url to call
   * @response the response from api call
   * @param expires e.g. '+1 day' or '+1 minute'
   */

  public function store($url, $response, $expires = '+1 day') {
    try {
      $resource = new Resource($url, $response, $expires);
      $sql = $resource->getInsertStatement();
      $ret = $this->db->exec($sql);
    } catch (PDOException $e) {
      dump('Exception : ' . $e->getMessage());
      die();
    }
  }

  // unused for now   
  public function fetchAll() {
    if (!$this->active) {
      return false;
    }
    $stmt = $this->db->query('SELECT url, response, expires FROM resources');
    $ret = $stmt->execute();
    if ($ret) {
      $resources = [];
      while ($resource = $stmt->fetchObject('Resource')) {
        $resources[$resource->url] = $resource->response;
      }
    } else
      $resources = false;
    return $resources;
  }

  // get from cache
  // if cache is off, or cache is expired, return false
  public function fetch($url) {
    if (!$this->active) {
      return false;
    }
    $stmt = $this->db->prepare('SELECT url, response, expires FROM resources WHERE url=?');
    $ret = $stmt->execute([$url]);
    $resource = false;
    if ($ret) {
      $objResult = $stmt->fetchObject('Resource');
      if (is_object($objResult)) {
        $isExpired = (float) $objResult->expires < (float) strtotime('now');
        if (!$isExpired) {
          $resource = json_decode($objResult->response);
        }
      }
    }
    return $resource;
  }

}

?>
