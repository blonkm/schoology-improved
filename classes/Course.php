<?
/** 
 *  schoology API calls to make groups
 *  or query groups and group members
 */
class Course {

    const API_KEY = 'YOUR API KEY HERE'; //enter your API key here
    const API_SECRET = 'YOUR API SECRET HERE'; //enter your API key here
    const API_BASE = 'https://api.schoology.com/v1';
    const MAX_ASSIGNMENTS = 400; // arbitrary to limit download time
    
    private $_app;
    private $_errors;
    
    /** singleton for the Schoology app object
     *  created and logged in, ready to fire off calls
     */
    function app() {
        if (!empty($this->_app))
            return $this->_app;

        // Replace these values with your application's consumer key and secret
        $consumer_key = self::API_KEY;
        $consumer_secret = self::API_SECRET;
        // Initialize the Schoology class
        $schoology = new SchoologyApi($consumer_key, $consumer_secret, '', '','', TRUE);

        // Initialize session handling
        session_start();

        // Read the incoming login information.
        $login = $schoology->validateLogin();

        $this->_errors=[];
        $this->_app = $schoology;

        return $schoology;
    }

    /* add another error to the list */
    function addError($message) {       
        $this->_errors[] = $message;
    }

    /* show error messages in HTML */
    function showErrors() {     
        foreach ($this->_errors as $message)
            echo '<p class="error">' . $message . '</p>';
    }

    /* specific for EPI, Aruba
    * convert short id to long id T15-0001
    * @param shortId an ID like T15001 to be converted
    * returns a long id with '-' and '0' like T15-0001
    */
    function toLongId($shortId) {
        return substr($shortId,0,3) . "-0" . substr($shortId, -3);
    }

    function removeBase($url) {
        return str_replace(self::API_BASE, '', $url);
    }

    function mkdir($folder) {
        if (!is_dir($folder)) {
            mkdir($folder, 0755, true);
        }
    }

    /** 
     *  Get course section Info
     *  @param sectionId
     *  @returns the course object
     */    
    function getSectionInfo($sectionId) {
        $schoology = $this->app();
        $url = 'sections/{id}';
        $url = str_replace('{id}', $sectionId, $url);

        // do the call
        $response = $schoology->api($url, 'GET');
        $sectionInfo = $response->result;

        return $sectionInfo;    
    }
    
    /** 
     *  Get list of section assignments
     *  for a maximum of 400 assignments
     *  @param sectionId
     *  @returns array of assignments for the section
     */    
    function getSectionAssignments($sectionId) {
        $schoology = $this->app();
        $url = 'sections/{section_id}/assignments?limit=' . self::MAX_ASSIGNMENTS;
        $url = str_replace('{section_id}', $sectionId, $url);

        // do the call
        $response = $schoology->api($url, 'GET');
        $result = $response->result;
        $assignments = [];
        foreach ($result->assignment as $assignment) {
            if ($assignment->completion_status == "Must make a submission") {
                $assignments[$assignment->id] = $assignment->title;
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
        $schoology = $this->app();
        $url = 'users/{id}';
        $url = str_replace('{id}', $userId, $url);

        // do the call
        $response = $schoology->api($url, 'GET');
        $userInfo = $response->result;
        return $userInfo;    
    }
    
    /** 
     *  Put members in groups
     *  this is a one time thing for the course
     *  IDs are students schoology IDs
     *  the api uses enrollment ids
     *  you can generate it using an Excel file or script
     */    
    function groupUsers() {
        // set groups
        $groupsAsText = array();

        $groups=[];

        // import group definitions
        include('groups_import.php');   
        
        return $groups;
    }

    
    /** 
     *  Convert user ids to enrollment ids
     *  @param lookup an array of schoology user ids
     *  @returns array of enrollment ids
     */    
    function groupEnrollments($lookup) {
        $groupUsers = $this->groupUsers();
        $enrollments = array();
        foreach ($groupUsers as $key=>$users) {
            $enrollments[$key] = array();
            foreach ($users as $user) {
                if (array_key_exists($user, $lookup))
                    $enrollments[$key][] = $lookup[$user];
                else {
                    $userInfo = $this->getUserInfo($user);
                    $first = $userInfo->name_first;
                    $last = $userInfo->name_last;
                    $id = $userInfo->id;
                    $schoolId = $this->toLongId($userInfo->school_uid);
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
        foreach ($members as $key=>$memberList) {
            $groups[] = (object) ['title'=>$key, 'members'=> $members[$key]];
        }

        return $groups;
    }

    // get enrollment data as complete object for the members in a section
    function getMemberObjects($sectionId) {
        return $this->listMembers($sectionId, true);
    }
    
    // get enrollment data as single id for the members in a section
    function listMembers($sectionId, $asObject = false) {
        $schoology = $this->app();
        $url = 'sections/{section_id}/enrollments?enrollment_status=1&limit=400';
        $url = str_replace('{section_id}', $sectionId, $url);

        // do the call
        $response = $schoology->api($url, 'GET');
        $members = $response->result;
        foreach($members->enrollment as $enrollment) {
            if ($asObject) {
                $userList[$enrollment->uid] = $enrollment;            
            }
            else {
                $userList[$enrollment->uid] = $enrollment->id;
            }
        }
        return $userList;
    }

    /** 
     *  Create grading groups
     *  
     *  @param sectionId the section in which we should make groups
     *  @caveats works only as one group at a time. 
     *  @see https://support.schoology.com/hc/en-us/requests/101876
     */    
    function createGradingGroups($sectionId) {
        $schoology = $this->app();
        
        // convert user ids to enrollment ids
        $lookup = $this->listMembers($sectionId);
        $memberObjects = $this->getMemberObjects($sectionId);
        $groups = $this->groups($lookup);
        
        foreach ($groups as $group) {
            $request = (object)$group;
            $json = json_encode($request);
            $pretty_json = json_encode($request, JSON_PRETTY_PRINT + JSON_NUMERIC_CHECK);

            $url = 'sections/{section_id}/grading_groups';
            $url = str_replace('{section_id}', $sectionId, $url);

            // do the call
            $response = $schoology->api($url, 'POST', $json);
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
        
        // convert user ids to enrollment ids
        $lookup = $this->listMembers($sectionId);
        $memberObjects = $this->getMemberObjects($sectionId);
        $groups = $this->groups($lookup);
        
        $gradingGroups = $this->listGradingGroups($sectionId);

        foreach ($groups as $group) {
            if (array_key_exists($group->title, $gradingGroups)) {
                $groupId = $gradingGroups[$group->title];
                $url = 'sections/{section_id}/grading_groups/{gg_id}';
                $url = str_replace('{section_id}', $sectionId, $url);
                $url = str_replace('{gg_id}', $groupId, $url);  
                // do the call
                $response = $schoology->api($url, 'DELETE');
            }
        }
    }
    
    // make an array of grading groups
    // with group names as keys
    // so we can easily look up the ids
    function listGradingGroups($sectionId) {
        $schoology = $this->app();
        
        $url = 'sections/{section_id}/grading_groups';
        $url = str_replace('{section_id}', $sectionId, $url);
        $response = $schoology->api($url, 'GET');       

        $groupList = array();
        foreach($response->result->grading_groups as $group) {
            $groupList[$group->title] = $group->id;
        }

        return $groupList;
    }

    /* make an array of grading groups
    * with group names as keys
    * and members as sub array
    */
    function listAllGradingGroupMembers($sectionId) {
        $schoology = $this->app();
        $members = array_flip($this->listMembers($sectionId));
        $url = 'sections/{section_id}/grading_groups';
        $url = str_replace('{section_id}', $sectionId, $url);
        $response = $schoology->api($url, 'GET');       
        
        foreach($response->result->grading_groups as $id=>$group) {            
            foreach ($group->members as $key=>$member) {
                $response->result->grading_groups[$id]->members[$key] = $this->getUserInfo($members[$member]);
            }
        }              
        return $response->result->grading_groups;
    }

    function getGrade($sectionId, $assignmentId, $enrollmentId) {
        $schoology = $this->app();
        $members = array_flip($this->listMembers($sectionId));
        
        $url = 'sections/{section_id}/grades?assignment_id={assignment_id}&enrollment_id={enrollment_id}';
        $url = str_replace('{assignment_id}', $assignmentId, $url);
        $url = str_replace('{enrollment_id}', $enrollmentId, $url);
        $url = str_replace('{section_id}', $sectionId, $url);
        $response = $schoology->api($url, 'GET');       
        
        return $response->result->grades->grade[0]->grade;
    }
    
    // make a list of all members of a specific group
    function listGradingGroupMembers($sectionId, $groupName = '') {
        $schoology = $this->app();
        $members = array_flip($this->listMembers($sectionId));
        
        $url = 'sections/{section_id}/grading_groups';
        $url = str_replace('{section_id}', $sectionId, $url);
        $response = $schoology->api($url, 'GET');       

        // delete unrequested groups, there is no other way to individually poll group members
        if ($groupName!='') {
            foreach($response->result->grading_groups as $id=>$group) {            
                if ($group->title != $groupName) {
                    unset($response->result->grading_groups[$id]);
                }
            }            
        }
        
        // add member info
        foreach($response->result->grading_groups as $id=>$group) {            
            foreach ($group->members as $key=>$member) {
                $response->result->grading_groups[$id]->members[$key] = $this->getUserInfo($members[$member]);
            }
        }
        
        return $response->result->grading_groups;
    }

    // make an array of submissions
    // for a specific user
    function listFilesOfUser($sectionId, $member) {
        $assignments = $this->getSectionAssignments($sectionId);
        $enrollments = $this->listMembers($sectionId);
        $schoology = $this->app();
        $files = array();
        
        foreach ($assignments as $id=>$assignment) {
            $url = 'sections/{section_id}/submissions/{grade_item_id}/{user_id}/revisions?with_attachments=1';
            $url = str_replace('{section_id}', $sectionId, $url);
            $url = str_replace('{grade_item_id}', $id, $url);
            $url = str_replace('{user_id}', $member->uid, $url);
            $response = $schoology->api($url, 'GET');
            foreach ($response->result->revision as $revision) { 
                foreach ($revision->attachments->files as $file) {      
                        $f = current($file);                        
                        $objFile = new stdClass;
                        $objFile->grade = $this->getGrade($sectionId, $id, $enrollments[$member->uid]);                        
                        $objFile->member = $member;
                        $objFile->userId = $this->toLongId($member->school_uid);
                        $objFile->first_name = $member->name_first;
                        $objFile->last_name = $member->name_last;
                        $objFile->revision = $revision->revision_id;
                        $MB = 1024*1024;
                        $objFile->size = round($revision->attachments->files->file[0]->filesize / $MB, 1);
                        $objFile->apiUrl = $this->removeBase($f->download_path);
                        $objFile->url = str_replace('api.schoology.com/v1','app.schoology.com', $f->download_path);                        
                        $objFile->name = $f->title;
                        $objFile->datetime = date('Y-m-d H:i:s', $f->timestamp);
                        $fileNameParts = [$assignments[$id], $objFile->last_name, $objFile->first_name, $objFile->userId, $objFile->revision, $objFile->name];
                        $fileName = join(' ', $fileNameParts);
                        $sanitized = preg_replace('/[^a-zA-Z0-9\-\._]/', '-', $fileName);
                        $objFile->saveAs = $sanitized;

                        $objFile->assignmentId = $id;
                        $files[] = $objFile;
                }
            }
        }
        return $files;
    }
    
    // make an array of submissions
    // of an assignment for each member of the group
    function listFilesOfGroupMembers($sectionId, $groupName, $assignment) {
        // get members
        $groups = $this->listGradingGroupMembers($sectionId, $groupName);
        $enrollments = $this->listMembers($sectionId);
        $schoology = $this->app();
        
        // get submissions per member
        $files = array();
        
        $users = [];
        foreach ($groups as $group) 
            foreach ($group->members as $member) {
                $url = 'sections/{section_id}/submissions/{grade_item_id}/{user_id}/revisions?with_attachments=1';
                $url = str_replace('{section_id}', $sectionId, $url);
                $url = str_replace('{grade_item_id}', $assignment, $url);
                $url = str_replace('{user_id}', $member->uid, $url);
                $response = $schoology->api($url, 'GET');
                foreach ($response->result->revision as $revision) { 
                    foreach ($revision->attachments->files as $file) {
                        $f = current($file);                        
                        $objFile = new stdClass;
                        $objFile->grade = $this->getGrade($sectionId, $assignment, $enrollments[$member->uid]);
                        $objFile->member = $member;
                        $objFile->userId = $this->toLongId($member->school_uid);
                        $objFile->group = $groupName;
                        $objFile->first_name = $member->name_first;
                        $objFile->last_name = $member->name_last;
                        $objFile->revision = $revision->revision_id;
                        $MB = 1024*1024;
                        $objFile->size = round($revision->attachments->files->file[0]->filesize / $MB, 1);
                        $objFile->apiUrl = $this->removeBase($f->download_path);
                        $objFile->url = str_replace('api.schoology.com/v1','app.schoology.com', $f->download_path);                        
                        $objFile->name = $f->title;
                        $objFile->datetime = date('Y-m-d H:i:s', $f->timestamp);
                        $fileNameParts = [$objFile->last_name, $objFile->first_name, $objFile->userId, $objFile->revision, $objFile->name];
                        $fileName = join(' ', $fileNameParts);
                        $sanitized = preg_replace('/[^a-zA-Z0-9\-\._]/', '-', $fileName);
                        $objFile->saveAs = $sanitized;
                        $objFile->assignmentId = $assignment;
                        $files[] = $objFile;
                    }
                }
        }
        return $files;
    }

    // save all attachments for a specific group and assignment
    function saveAttachments($files, $assignment) {
        $downloadsFolder = 'downloads';
        foreach ($files as $file) {
            $filename = str_replace(' ', '-', $file->group . '-' . $file->last_name . '-' . $file->first_name . '-' . $file->userId . '-' . $file->revision . '-' . $file->name);
            $sanitized = preg_replace('/[^a-zA-Z0-9\-\._]/', '-', $assignment);
            $groupFolder = $downloadsFolder . '/' . $sanitized . '/' . $file->group;
            $this->mkdir($groupFolder);
            $schoology = $this->app();
            $response = $schoology->api($file->apiUrl, 'GET');
            $sanitized_filename = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $filename);
            $filePath = $groupFolder . '/' . $sanitized_filename;
            // download and save raw file
            if (!file_exists($filePath)) {
                file_put_contents($filePath, file_get_contents($response->redirect_url));
            }
        }
    }

    // save all attachments for a specific user
    function savePortfolio($files, $member, $assignments) {
        $downloadsFolder = 'downloads';
        foreach ($files as $file) {
            $filename = str_replace(' ', '-', $assignments[$file->assignmentId] . '-' . $file->last_name . '-' . $file->first_name . '-' . $file->userId . '-' . $file->revision . '-' . $file->name);
            $sanitized_filename = iconv("UTF-8", "ISO-8859-1//TRANSLIT", $filename);
            $userFolder = str_replace(' ', '-', $downloadsFolder . '/' . strtoupper($member->username) . '-' . $member->name_last . '-' . $member->name_first);
            $this->mkdir($userFolder);
            $schoology = $this->app();
            $response = $schoology->api($file->apiUrl, 'GET');
            $filePath = $userFolder . '/' . $sanitized_filename;
            // download and save raw file
            if (!file_exists($filePath)) {
                file_put_contents($filePath, file_get_contents($response->redirect_url));
            }
        }
    }

    // create a zip file containing a specific
    // list of files from all or one group for an assignment 
    function download($section, $member, $assignment, $group) {
        $folder = 'downloads';
        $zipFile = 'download';
        if (isset($member)) {
            $schoolId = strtoupper($this->toLongId($member->school_uid));
            $folder .= '/' . str_replace(' ', '-', $schoolId . '-' . $member->name_last . '-' . $member->name_first);
            $zipFile = $folder;
        }
        else {
            if (isset($assignment)) {
                $zipFile = preg_replace('/[^a-zA-Z0-9\-\._]/', '-', $assignment);
                $folder .= '/' . $zipFile;
            }
            if (isset($group)) {
                $zipFile .= '-' . $group;
                $folder .= '/' . $group;
            }           
        }
        $zipFile .= '.zip';
        // delete previous download file
        if (file_exists($zipFile)) {
            unlink($zipFile);
        }
        // build download file
        $objArchive = new FlxZipArchive;
        $canZip = $objArchive->open($zipFile, ZipArchive::CREATE);
        if($canZip)    {
            $objArchive->addDir($folder, basename($folder)); 
            $objArchive->close();
            header('Content-Type: application/zip');
            header("Content-Disposition: attachment; filename=" . "'" . $zipFile . "'");
            header('Content-Length: ' . filesize($zipFile));
            header("Location: " . $zipFile);
            return true;
        }
        else  { 
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
        foreach($groups as $group) {
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
    
    // test if we can actually post something to schoology
    // result: yes we can
    function testCreateGroup() {
        $query = '{"title": "My new group","description": "discuss new groups","website": "http:\/\/www.newgroup.com"}';
        $schoology = $this->app();
        $response = $schoology->api('groups', 'POST', $query);
        dump(json_decode($response->result));
    }
}
?>
