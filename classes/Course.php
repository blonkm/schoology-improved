<?

/**
 *  Schoology API calls to make groups
 *  or query groups and group members
 *  @author Michiel van der Blonk (blonkm@gmail.com)
 *  @date 2017-11-24
 */
class Course {

  const API_BASE = 'https://api.schoology.com/v1';
  const API_URL = 'https://api.schoology.com';
  const WEB_BASE = 'https://www.schoology.com';
  const LIMIT = 400; // arbitrary to limit download time
  const DATETIME_FORMAT = 'D d-M-Y h:i A'; // Mon 2017-nov-20 8:50 AM
  const DATE_FORMAT = 'D Y-M-d'; // Mon 2017-nov-20
  const TIME_FORMAT = 'h:i A'; // 8:50AM
  const STATUS_TEXT = ['View the item', 'Make a submission', 'Score at least'];
  const STATUS_MUST_VIEW_THE_ITEM = 0;
  const STATUS_MAKE_A_SUBMISSION = 1;
  const STATUS_SCORE_AT_LEAST = 2;
  const STATUS_ALL = 3;
  const STATUS_ALL_TEXT = "";
  const STATUS_CHECK_PERIOD = 5;

  private static $API_KEY;
  private static $API_SECRET;
  private $_app;
  private $_errors = [];
  private $_apiCallCount = 0;
  private $_cache;
  private $_timer;
  private $_followRedirects = false;

  function __construct() {
    $this->_timer = new Timer(true);
    $this->_timer->setThrottleRate(50); // allow no more than 50 api calls per second
    $FS = new Filesystem;
    self::$API_KEY = $FS->read('./config/.api_key');
    self::$API_SECRET = $FS->read('./config/.api_secret');
  }

  function getStatusAsText($status) {
    $statusAsText = ['Must view the item', 'Must make a submission', 'Must score at least'];
    switch (intval($status)) {
      case self::STATUS_MUST_VIEW_THE_ITEM :
      case self::STATUS_MAKE_A_SUBMISSION:
      case self::STATUS_SCORE_AT_LEAST:
        return $statusAsText[intval($status)];
      default:
        return '';
    }
  }

  /** singleton for the Schoology app object
   *  created and logged in, ready to fire off calls
   */
  function app() {
    if (!empty($this->_app))
      return $this->_app;

    // Replace these values with your application's consumer key and secret
    $consumer_key = self::$API_KEY;
    $consumer_secret = self::$API_SECRET;

    $schoology = new SchoologyApi($consumer_key, $consumer_secret, '', '', '', TRUE);
    $login = $schoology->validateLogin();
    $this->_errors = [];
    $this->_app = $schoology;
    return $schoology;
  }

  function cache() {
    if (!empty($this->_cache))
      return $this->_cache;
    $this->_cache = new Cache;
    return $this->_cache;
  }

  function disableCache() {
    $cache = $this->cache();
    $cache->disable();
  }

  function enableCache() {
    $cache = $this->cache();
    $cache->enable();
  }

  function setCache($active) {
      if ($active)
        $this->enableCache();
      else
        $this->disableCache();
  }

  function refreshCache($numRecords) {
    $records = $this->cache()->fetchExpired($numRecords);
    foreach ($records as $url => $resource) {
      $this->api($url);
    }
    return $records;
  }
  
  function apiCounter() {
    return $this->_apiCallCount;
  }

  function getResource($url) {
    $cache = $this->cache();
    return $cache->fetch($url);
  }

  function saveResource($url, $resource, $expires) {
    $cache = $this->cache();
    return $cache->store($url, $resource, $expires);
  }

  // first check if the api is available for requests
  function checkApiStatus() {
    // only call once every STATUS_CHECK_PERIOD seconds in order to avoid 
    // the page repeatedly checking for api availability
    if (time() - $_SESSION['lastApiStatusCheck'] < self::STATUS_CHECK_PERIOD) 
      return true;
    $url = self::API_URL;
    $timeout = ini_get('default_socket_timeout');
    ini_set('default_socket_timeout', 2);
    $headers = @get_headers($url);   
    $_SESSION['lastApiStatusCheck'] = time();
    ini_set('default_socket_timeout', $timeout);    
    if($headers && strpos( $headers[0], '302')) { // schoology returns 302 normally
      return true;
    } 
    else { 
      return false;
    } 
  }
  
  function api($url, $expires = '+1 year', $method = 'GET') {
    if (@$this->checkApiStatus() == false) {
      if (!$this->hasErrors()) {
        $this->addError("Warning: Schoology api not available");
      }
    }
    $resource = $this->getResource($url);
    if (!$resource) {
      $schoology = $this->app();
      $this->_timer->throttle();
      $url = str_replace('//','/',$url);      
      if ($this->_followRedirects == true)
        $resource = $schoology->apiResult($url, $method);
      else
        $resource = $schoology->api($url, $method);
      $this->_timer->setStartTime();
      $this->_apiCallCount++;
      $this->saveResource($url, $resource, $expires);
    }
    return $resource;
  }

  /* add another error to the list */

  function addError($message) {
    $this->_errors[] = $message;
  }

  public function hasErrors() {
    return count($this->_errors) > 0;
  }
  
  function getFirstError() {
    if ($this->hasErrors()) {
      return $this->_errors[0];
    }
  }
  
  /* show error messages in HTML */
  function showErrors() {
    foreach ($this->_errors as $message)
      echo '<p class="error">' . $message . '</p>';
  }

  function getSections($courseId) {
    $url = 'courses/{id}/sections';
    $url = str_replace('{id}', $courseId, $url);

    // do the call
    $response = $this->api($url);
    $sections = $response->result;
    return $sections;
  }

  /**
   *  Get courses
   *  @param 
   *  @returns the courses
   */
  function getCourses($withSections = true, $onlyActive = true) {
    $url = 'courses&limit=400';

    // do the call     
    $response = $this->api($url);
    $result = $response->result;
    $courses = [];
    foreach ($result->course as $course) {
      $sections = $this->getSections((string) $course->id);
      $addSection = $onlyActive == true && count($sections->section) > 0;
      if ($withSections && $addSection) {
        $course->sections = $sections->section;
      }
      if ($addSection || $onlyActive == false) {
        $courses[] = $course;
      }
    }

    return $courses;
  }

  /**
   *  Get course section Info
   *  @param sectionId
   *  @returns the course objectxx
   */
  function getSectionInfo($sectionId) {
    $url = 'sections/{id}';
    $url = str_replace('{id}', $sectionId, $url);

    // do the call
    $response = $this->api($url);
    $sectionInfo = $response->result;

    return $sectionInfo;
  }

  /**
   *  Get list of section assignments
   *  for a maximum of LIMIT (initially 400) assignments
   *  @param sectionId
   *  @returns array of assignments for the section
   */
  function getSectionAssignments($sectionId, $completionStatus = self::STATUS_MAKE_A_SUBMISSION, $category = null) {
    $statusAsText = $this->getStatusAsText($completionStatus);
    $url = 'sections/{section_id}/assignments?limit=' . self::LIMIT;
    $url = str_replace('{section_id}', $sectionId, $url);

    // do the call
    $response = $this->api($url);
    if (!$response) 
      return false;
    $result = $response->result;
    $assignments = [];
    foreach ($result->assignment as $assignment) {
      $isSelectedStatus = $assignment->completion_status == $statusAsText || $completionStatus == self::STATUS_ALL;
      $isSelectedCategory = ($assignment->grading_category == $category) || is_null($category);
      if ($isSelectedStatus && $isSelectedCategory) {
        $assignments[(string) $assignment->id] = $assignment->title;
      }
    }
    return $assignments;
  }

  /**
   *  Get User Info
   *  @param userId, the schoology unique user ID
   *  @returns the user object
   */
  function getUserInfo($userId) {
    $url = 'users/{id}?picture_size=big';
    $url = str_replace('{id}', $userId, $url);

    // do the call
    $response = $this->api($url);
    $userInfo = $response->result;
    return $userInfo;
  }

  /**
   *  Get User Info by School Student ID
   *  @param userId, the school system ID
   *  @returns the user object
   */
  function getUserInfoBySchoolId($userId) {
    $url = 'users/ext/{id}';
    $url = str_replace('{id}', strtoupper($userId), $url);
    // do the call, and follow redirects
    // which we normally don't (since it's easier)
    $this->_followRedirects = true;
    $response = $this->api($url);
    $this->_followRedirects = false;
    $userInfo = $response;
    return $userInfo;
  }

  /**
   *  Get User Last Login Info
   *  @param userId, the schoology ID
   *  @returns the user object
   */
  function getUserLastLoginInfo($userId) {
    $url = 'analytics/users/{id}?start_time={start}&end_time={end}';
    $start = strtotime('-7 days');
    $end = strtotime('now');
    $url = str_replace('{id}', $userId, $url);
    $url = str_replace('{start}', $start, $url);
    $url = str_replace('{end}', $end, $url);

    // do the call
    $response = $this->api($url);
    $userInfo = $response->result;
    return $userInfo;
  }

  /**
   *  Get User Last Login Info
   *  @param userId, the schoology ID
   *  @returns the user object
   */
  function getAnalytics($sectionId) {
    $url = 'analytics/highlights/sections/{section_id}';
    $url = str_replace('{section_id}', $sectionId, $url);

    // do the call
    $response = $this->api($url);
    $result = $response->result;
    $analytics = [];

    foreach ($result->highlights as $login) {
      $analytics[(string) $login->uid] = $login->last_login;
    }
    return $analytics;
  }

  /**
   * get all enrollments who are not in a grading group
   * 
   * @param type $sectionId the section
   */
  function getOrphans($sectionId) {
    // get everyone in the course
    $enrollments = $this->getMemberObjects($sectionId);
    
    // get everyone who is in a group (hierarchical groups with members)
    $groups = $this->listAllGradingGroupMembers($sectionId);
    
    // flatten groups to list of members
    $members = [];
    foreach($groups as $group) {
      foreach($group->members as $member) {
        $members[$member->id] = $member;
      }
    }
    
    // loop and find all enrollments not in the member list
    $orphanEnrollments = [];
    foreach ($enrollments as $enrollment) {
      if (!key_exists($enrollment->uid, $members)) {
        $orphanEnrollments[] = $enrollment;
      }    
    }

    // convert enrollment objects to user objects
    $orphans = [];
    foreach($orphanEnrollments as $orphan) {
      $member = $this->getUserInfo($orphan->uid);
      $user = (object) ([
          'group' => '',
          'first_name' => $member->name_first,
          'last_name' => $member->name_last,
          'username' => strtoupper($member->username),
          'info' => $this->getUserInfo($orphan->uid)]);
      $orphans[$orphan->uid] = $user;
    }

    return $orphans;
  }
  
  /**
   *  Convert user ids to enrollment ids
   *  @param lookup an array of schoology user ids
   *  @returns array of enrollment ids
   */
  function groupEnrollments($lookup) {
    $groupUsers = $this->groupUsers();
    $enrollments = array();
    foreach ($groupUsers as $key => $users) {
      $enrollments[$key] = array();
      foreach ($users as $user) {
        if (array_key_exists($user, $lookup))
          $enrollments[(string) $key][] = $lookup[$user];
        else {          
          $userInfo = $this->getUserInfo($user);
          $first = $userInfo->name_first;
          $last = $userInfo->name_last;
          $id = $userInfo->id;
          $schoolId = formatId($userInfo->school_uid);
          $this->addError("user not found: $first $last ( $id - $schoolId)<br/>");
        }
      }
    }
    return $enrollments;
  }

  /**
   *  Get groups
   *  
   *  @param lookup an array of user ids
   *  @returns array of group objects
   */
  function groups($lookup) {
    // create groups
    $members = $this->groupEnrollments($lookup);
    foreach ($members as $key => $memberList) {
      $groups[] = (object) ['title' => $key, 'members' => $members[$key]];
    }
    return $groups;
  }

  // get enrollment data as complete object for the members in a section
  function getMemberObjects($sectionId) {
    return $this->listMembers($sectionId, true);
  }

  // get enrollment data as single id for the members in a section
  function listMembers($sectionId, $asObject = false) {
    $url = 'sections/{section_id}/enrollments?enrollment_status=1&limit=400';
    $url = str_replace('{section_id}', $sectionId, $url);
    $userList = [];

    // do the call
    $response = $this->api($url);
    $members = $response->result;
    foreach ($members->enrollment as $enrollment) {
      if ($asObject) {
        $userList[(string) $enrollment->uid] = $enrollment;
      } else {
        $userList[(string) $enrollment->uid] = $enrollment->id;
      }
    }
    return $userList;
  }

  /**
   *  Create grading groups
   *  
   *  @param $sectionId the section in which we should make groups
   *  @param $import array in format [uniqueId, groupName]
   *  @caveats works only as one group at a time. 
   *  @see https://support.schoology.com/hc/en-us/requests/101876
   */
  function createGradingGroups($sectionId, $import) {
    if (empty($sectionId)) {
      throw new Exception('section id required in createGradingGroups');
    }
    if (empty($import)) {
      throw new Exception('import data empty in createGradingGroups');
    }
    
    // find all members (enrollment ids)
    $memberObjects = $this->getMemberObjects($sectionId);
    foreach ($memberObjects as $member) {
      $enrollments[$member->uid] = $member->id;
    }

    // filter by members who need to be placed in groups (imported)
    $find_user = function($item) use ($import) { return isset($import[$item]); };
    $users = array_filter($enrollments, $find_user, ARRAY_FILTER_USE_KEY);

    $usersWithGroups = [];
    // add the group to enrollment ids
    foreach ($users as $id=>$user) {
      $usersWithGroup[] = (object)["id"=>$user, "group"=>$import[$id]];
    }
    
    // convert to nested array
    $groupArray=[];
    foreach ($usersWithGroup as $key=>$user) {
      if (!array_key_exists($user->group, $groupArray)) {
        $groupArray[$user->group] = [];
      }
      array_push($groupArray[$user->group], $user->id);
    }

    $groups = [];
    // convert to nested objects
    foreach ($groupArray as $key => $members) {
      $groups[] = (object) ['title' => $key, 'members' => $members];
    }

    // sort groups by name to make overview in schoology more logical
    usort($groups,function($group1,$group2){
        return strcmp($group1->title, $group2->title);
    });    

    // call api to add groups with their respective members
    foreach ($groups as $group) {
      $request = (object) $group;
      $json = json_encode($request);
      
      $url = 'sections/{section_id}/grading_groups';
      $url = str_replace('{section_id}', $sectionId, $url);

      // do the call
      $schoology = $this->app();
      $schoology->api($url, 'POST', $json);
      usleep(200 * 1000); // wait 200ms 
    }
  }

  /**
   *  Delete all grading groups
   *  
   *  @param sectionId the section in which we should make groups
   *  @caveats Careful: no warning and deletes all groups at once
   */
  function deleteAllGroups($sectionId) {
    $schoology = $this->app();
    $this->disableCache();
    $gradingGroups = $this->listGradingGroups($sectionId);
    foreach ($gradingGroups as $groupId) {
      $url = 'sections/{section_id}/grading_groups/{gg_id}';
      $url = str_replace('{section_id}', $sectionId, $url);
      $url = str_replace('{gg_id}', $groupId, $url);
      // do the call
      try {
        $schoology->api($url, 'DELETE');
      } catch (Exception $e) {
          dump(time() . $e->getMessage());
      }

      usleep(200 * 1000); // wait 200ms 
    }
    $this->enableCache();
  }

  // make an array of grading groups
  // with group names as keys
  // so we can easily look up the ids
  function listGradingGroups($sectionId) {
    $url = 'sections/{section_id}/grading_groups';
    $url = str_replace('{section_id}', $sectionId, $url);
    $response = $this->api($url);

    $groupList = array();
    foreach ($response->result->grading_groups as $group) {
      $groupList[$group->title] = $group->id;
    }

    return $groupList;
  }

  /* make an array of grading groups
   * with group names as keys
   * and members as sub array
   */

  function listAllGradingGroupMembers($sectionId) {
    $members = array_flip($this->listMembers($sectionId));
    $url = 'sections/{section_id}/grading_groups';
    $url = str_replace('{section_id}', $sectionId, $url);
    $response = $this->api($url);

    foreach ($response->result->grading_groups as $id => $group) {
      foreach ($group->members as $key => $member) {
        $memberIdAsString = number_format($member, 0, '.', '');
        if (array_key_exists((string) $member, $members)) {
          $response->result->grading_groups[$id]->members[(string) $key] = $this->getUserInfo($members[(string) $member]);
        }
      }
    }
    return $response->result->grading_groups;
  }

  function getGrade($sectionId, $assignmentId, $enrollmentId) {
    $members = array_flip($this->listMembers($sectionId));

    $url = 'sections/{section_id}/grades?assignment_id={assignment_id}&enrollment_id={enrollment_id}';
    $url = str_replace('{assignment_id}', $assignmentId, $url);
    $url = str_replace('{enrollment_id}', $enrollmentId, $url);
    $url = str_replace('{section_id}', $sectionId, $url);
    $response = $this->api($url, '+1 minute');
    return $response->result->grades->grade[0]->grade;
  }

  function getComments($sectionId, $assignmentId, $enrollmentId) {
    $members = array_flip($this->listMembers($sectionId));

    $url = 'sections/{section_id}/grades?assignment_id={assignment_id}&enrollment_id={enrollment_id}';
    $url = str_replace('{assignment_id}', $assignmentId, $url);
    $url = str_replace('{enrollment_id}', $enrollmentId, $url);
    $url = str_replace('{section_id}', $sectionId, $url);
    $response = $this->api($url, '+1 minute');
    return $response->result->grades->grade[0]->comment;
  }

  // make a list of all members of a specific group
  function listGradingGroupMembers($sectionId, $groupName = '') {
    $members = array_flip($this->listMembers($sectionId));

    $url = 'sections/{section_id}/grading_groups';
    $url = str_replace('{section_id}', $sectionId, $url);
    $response = $this->api($url);

    // delete unrequested groups, there is no other way to individually poll group members
    if ($groupName != '') {
      foreach ($response->result->grading_groups as $id => $group) {
        if ($group->title != $groupName) {
          unset($response->result->grading_groups[$id]);
        }
      }
    }

    // add member info
    foreach ($response->result->grading_groups as $id => $group) {
      foreach ($group->members as $key => $member) {
        $response->result->grading_groups[$id]->members[$key] = $this->getUserInfo($members[(string) $member]);
      }
    }

    return $response->result->grading_groups;
  }

  function getGradingCategories($sectionId) {
    $url = 'sections/{section_id}/grading_categories';
    $url = str_replace('{section_id}', $sectionId, $url);
    $response = $this->api($url);

    $categoryList = array();
    foreach ($response->result->grading_category as $category) {
      $categoryList[(string) $category->id] = $category->title;
    }

    return $categoryList;
  }

  // make an array of submissions
  // for a specific user
  function listFilesOfUser($sectionId, $member) {
    $assignments = $this->getSectionAssignments($sectionId);
    $enrollments = $this->listMembers($sectionId);
    $files = array();
    
    foreach ($assignments as $assignmentId => $assignmentTitle) {
      $url = 'sections/{section_id}/submissions/{grade_item_id}/{user_id}/revisions?with_attachments=1';
      $url = str_replace('{section_id}', $sectionId, $url);
      $url = str_replace('{grade_item_id}', $assignmentId, $url);
      $url = str_replace('{user_id}', $member->uid, $url);
      $response = $this->api($url, '+1 minute');
      foreach ($response->result->revision as $revision) {
        foreach ($revision->attachments->files as $downloads) {
          foreach ($downloads as $file) {
            $objSubmission = (new Submission)
                    ->setId((string) $file->id)
                    ->setSection($sectionId)
                    ->setCourse($this)
                    ->setAssignment($assignmentId)
                    ->setMember($member)
                    ->setFile($file)
                    ->setRevision($revision)
                    ->setFilename();
            $objSubmission->grade = $this->getGrade($sectionId, $assignmentId, $enrollments[(string) $member->uid]);
            $objSubmission->comment = $this->getComments($sectionId, $assignmentId, $enrollments[(string) $member->uid]);
            $files[] = $objSubmission;
          }
        }
      }
    }
    return $files;
  }

  // list properties of one file
  function listFilesOfGroupMember($sectionId, $groupName, $member, $assignmentId) {
    $files = [];
//    $groups = $this->listGradingGroupMembers($sectionId, $groupName);
    $enrollments = $this->listMembers($sectionId);
    
    $url = 'sections/{section_id}/submissions/{grade_item_id}/{user_id}/revisions?with_attachments=1';
    $url = str_replace('{section_id}', $sectionId, $url);
    $url = str_replace('{grade_item_id}', $assignmentId, $url);
    $url = str_replace('{user_id}', $member->uid, $url);   
    $response = $this->api($url, '+10 seconds');
    // check for failing api call
    if (!is_object($response->result)) {
      return [];
    }

    foreach ($response->result->revision as $revision) {
      foreach ($revision->attachments->files as $downloads) {
        foreach ($downloads as $download) {
          $file = $download;
          $objSubmission = (new Submission)
                  ->setId($download->id)
                  ->setSection($sectionId)
                  ->setCourse($this)
                  ->setAssignment($assignmentId)
                  ->setGroup($groupName)
                  ->setFile($file)
                  ->setMember($member)
                  ->setRevision($revision)
                  ->setFilename();
          $objSubmission->grade = $this->getGrade($sectionId, $assignmentId, $enrollments[(string) $member->uid]);
          $objSubmission->comment = $this->getComments($sectionId, $assignmentId, $enrollments[(string) $member->uid]);

          $files[] = $objSubmission;
        }
      }
    }
    return $files;
  }
  
  // make an array of submissions
  // of an assignment for each member of the group
  function listFilesOfGroupMembers($sectionId, $groupName, $assignmentId) {
    // get members
    $groups = $this->listGradingGroupMembers($sectionId, $groupName);

    // get submissions per member
    $files = array();
    $users = [];
    foreach ($groups as $group)
      foreach ($group->members as $member) {
        $memberFiles = $this->listFilesOfGroupMember($sectionId, $groupName, $member, $assignmentId);
        $files = array_merge($files, $memberFiles);
      }
    return $files;
  }

  function saveAttachment($file, $assignmentId) {
    $assignments = $this->getSectionAssignments($file->sectionId, self::STATUS_ALL);
    $downloadsFolder = 'downloads';
    $FS = new Filesystem;
    $filename = str_replace(' ', '-', $file->group . '-' . $file->last_name . '-' . $file->first_name . '-' . $file->userId . '-' . $file->revision . '-' . $file->name);
    $sanitized = $FS->sanitize($assignments[$assignmentId]);
    $sanitizedGroup = $FS->sanitize($file->group);
    $groupFolder = $downloadsFolder . '/' . $sanitized . '/' . $sanitizedGroup;
    $FS->mkdir($groupFolder);
    $response = $this->api($file->apiUrl, '+1 second');
    $sanitized_filename = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $filename);
    $filePath = $groupFolder . '/' . $sanitized_filename;
    // download and save raw file
    if (!file_exists($filePath)) {
      file_put_contents($filePath, file_get_contents($response->redirect_url));
    }
    return $filePath;
  }
  
// save all attachments for a specific group and assignment
  function saveAttachments($files, $assignment) {
    foreach ($files as $file) {
      $this->saveAttachment($file, $assignment);
    }    
  }

  // save all attachments for a specific user
  function savePortfolio($files, $member, $assignments) {
    $downloadsFolder = 'downloads';
    foreach ($files as $file) {
      $filename = str_replace(' ', '-', $assignments[(string) $file->assignmentId] . '-' . $file->last_name . '-' . $file->first_name . '-' . $file->userId . '-' . $file->revision . '-' . $file->name);
      $sanitized_filename = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $filename);
      $userFolder = str_replace(' ', '-', $downloadsFolder . '/' . strtoupper($member->username) . '-' . $member->name_last . '-' . $member->name_first);
      (new Filesystem)->mkdir($userFolder);
      $response = $this->api($file->apiUrl, '+1 second');
      $filePath = $userFolder . '/' . $sanitized_filename;
      // download and save raw file
      if (!file_exists($filePath)) {
        file_put_contents($filePath, file_get_contents($response->redirect_url));
      }
    }
  }

  // download one submission
  function downloadFile($section, $submission) {
    $response = $this->api('submission/' . $submission . '/source');
    header("Location: " . $response->redirect_url);
  }

  // create a zip file containing a specific
  // list of files from all or one group for an assignment 
  function download($section, $member, $assignment = null, $group = null) {
    $downloads = 'downloads/';
    $folder = '';
    $zipFile = '';
    $extension = '.zip';
    if (isset($member)) {
      $schoolId = strtoupper(formatId($member->school_uid));
      $folder = $downloads . '/' . str_replace(' ', '-', $schoolId . '-' . $member->name_last . '-' . $member->name_first);
      $zipFile = $folder . $extension;
    } else {
      if (isset($assignment)) {
        $folder .= $downloads . (new Filesystem)->sanitize($assignment);
        $zipFile = $folder . $extension;
      } else {
        if (isset($group)) {
          $sanitizedGroup = (new Filesystem)->sanitize($group);
          $folder = $downloads . $sanitizedGroup;
          $zipFile = $downloads . $sanitizedGroup . $extension;
        }
      }
    }
    // delete previous download file
    (new Filesystem)->unlink($zipFile);

    // build download file
    $objArchive = new FlxZipArchive;
    $canZip = $objArchive->open($zipFile, ZipArchive::CREATE);
    if ($canZip) {
      $objArchive->addDir($folder, basename($folder));
      $objArchive->close();
      header('Content-Type: application/zip');
      header("Content-Disposition: attachment; filename=" . "'" . $zipFile . "'");
      header('Content-Length: ' . filesize($zipFile));
      header("Location: " . $zipFile);
      return true;
    } else {
      $this->addError('Could not create a zip archive');
      return false;
    }
  }

  // for each known grading group
  // add its members to the group
  function addMembersToGradingGroups($sectionId) {
    $schoology = $this->app();

    // convert user ids to enrollment ids
    $lookup = $this->listMembers($sectionId);
    $groups = $this->groups($lookup);

    $gradingGroups = $this->listGradingGroups($sectionId);
    foreach ($groups as $group) {
      $groupId = $gradingGroups[$group->title];
      $request = (object) ["id" => $groupId, "title" => $group->title, "members" => $group->members];
      $json = json_encode($request);

      $url = 'sections/{section_id}/grading_groups/{gg_id}';
      $url = str_replace('{section_id}', $sectionId, $url);
      $url = str_replace('{gg_id}', $groupId, $url);
      // do the call
      $response = $schoology->api($url, 'PUT', $json);
    }
  }

  /* purge all downloads */
  function purgeDownloads() {
    $downloadsFolder = realpath('downloads');
    if (!$downloadsFolder)
      return;
    $objFilesystem = new Filesystem;
    $objFilesystem->deleteFiles($downloadsFolder);
    $this->_errors = $objFilesystem->getErrors();
    $objFilesystem->mkdir($downloadsFolder);
  }

  // upload a CSV file with members to the server
  // for it to be imported    
  // @return an array of group,member pairs
  function uploadMembersCsv() {
    $uploader = new Uploader('uploads');
    $fieldName = 'file';
    $type = 'csv';
    $targetFile = $uploader->upload($fieldName, $type);
    if (!$uploader->hasErrors()) {
      $csv = array_map('str_getcsv', file($targetFile));
      return $csv;
    } else {
      $this->_errors = $uploader->getErrors();     
      return [];
    }
  }
  
  /**
   * import a CSV file
   * 
   * @param type $sectionId
   * @param type $data -> uniqueID, userID, groupName, firstName, lastName
   * @return type
   */
  function importCsv($sectionId, $data) {
    array_shift($data); // remove headers
    $importData = [];
    foreach ($data as $entry) {
      $uniqueId = $entry[0];
      $groupName = $entry[2];
      $importData[$uniqueId] = $groupName;
    }
    $this->createGradingGroups($sectionId, $importData);
    return;
  }

}

?>
