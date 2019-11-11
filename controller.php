<?
/**
 * @author Michiel van der Blonk
 * @email blonkm@gmail.com
 * @version 1.0
 * Controls action parameter and dispatches to different 'views'
 */

/* get user data for known grading groups */
/* should this go into the Course class? */
function getAllUsers($groups) {
    $users = [];
    foreach ($groups as $group) {
        foreach ($group->members as $member) {
            $users[] = (object)([
                'group'=>$group->title, 
                'first_name'=>$member->name_first, 
                'last_name'=>$member->name_last,
                'username'=>strtoupper($member->username),
                'info'=>$member]);
            }
         }
    return $users;
}

/* main */
switch (strtolower($action)) {
    case 'members':
/*        $pageTitle = "Group members of section " . $sectionInfo->section_title;
        $members = $course->listMembers($section); 
        $users = $members;
        break;
*/
	case 'pictures':
    case 'courses':
      $courses = $course->getCourses();
      $pageTitle = "Select a course from available courses";
      break;
    case 'groups':
    case 'list':
    case 'code':
    case 'source':
    case '':
        $pageTitle = "Group members of section " . $sectionInfo->section_title;
        $groups = $course->listAllGradingGroupMembers($section);
        $users = getAllUsers($groups);         
        break;
    case 'attendance':
        $pageTitle = "Attendance for members of section " . $sectionInfo->section_title;
        $groups = $course->listAllGradingGroupMembers($section);
        $users = getAllUsers($groups);
        $analytics= $course->getAnalytics($section);
        $timetable = new Timetable;
        foreach ($users as $key=>$user) {
            if (isset($analytics[$user->info->uid]))
              $user->info->last_login = $analytics[$user->info->uid];
              try {
               $user->info->lesson = $timetable->lesson($user->info->last_login);
              }
              catch (Exception $e) {
                  $user->info->lesson = 'home';
              }
        }  
        break;
    case 'matrix':
        $pageTitle = "Assignments of section " . $sectionInfo->section_title;
        $groups = $course->listGradingGroups($section);        
        ksort($groups);
        $assignments = $course->getSectionAssignments($section, $status, $category);
        asort($assignments);
        break;
    case 'user':
        $assignments = $course->getSectionAssignments($section, Course::STATUS_ALL);
        $member = $course->getUserInfo($userid);
        $first = $member->name_first;
        $last = $member->name_last;
        $id = $member->id;
        $schoolId = formatId($member->school_uid);
        $pageTitle = "Assignments for user " . strtoupper($member->username) . " in course " . $sectionInfo->section_title;
        $files = $course->listFilesOfUser($section, $member);        
        break;
    case 'files':
        $pageTitle = "Submissions for " . $group . " in section " . $sectionInfo->section_title;
        $files = $course->listFilesOfGroupMembers($section, $group, $assignment);
        $assignments = $course->getSectionAssignments($section, Course::STATUS_ALL);
        break;
    case 'create':
        $pageTitle = "Creating groups for section " . $sectionInfo->section_title;
        $course->createGradingGroups($section);
        $course->addMembersToGradingGroups($section); 
        break;
    case 'delete':
        $pageTitle = "Deleting all groups of section " . $sectionInfo->section_title;
        $course->deleteAllGroups($section);
        break;
    case 'file':
/*
        // first download file to local server
        $pageTitle = "Downloading submission file " . $submission . " of assignment " . $assignment ;
        $files = $course->listFilesOfGroupMembers($section, $group, $assignment);
        $assignments = $course->getSectionAssignments($section, Course::STATUS_ALL);
        $course->saveAttachments($files, $assignments[$assignment]);
        if ($course->download($section, null, $assignments[$assignment], $group))
*/        
        $filesSaved = true;   
        if ($course->downloadFile($section, $submission))
            die(); // no more response, we're downloading a zip file            
        break;
    case 'download':
        $course->purgeDownloads();
        $pageTitle = "Downloading submissions of assignment " . $assignment;
        $files = $course->listFilesOfGroupMembers($section, $group, $assignment);
        $assignments = $course->getSectionAssignments($section, Course::STATUS_ALL);
        $course->saveAttachments($files, $assignments[$assignment]);
        $filesSaved = true;       
        if ($course->download($section, null, $assignments[$assignment], $group))
            die(); // no more response, we're downloading a zip file            
        break;
    case 'portfolio':
        $assignments = $course->getSectionAssignments($section, Course::STATUS_ALL);
        $member = $course->getUserInfo($userid);
        $first = $member->name_first;
        $last = $member->name_last;
        $id = $member->id;
        $schoolId = formatId($member->school_uid);
        $files = $course->listFilesOfUser($section, $member);        
        $pageTitle = "Downloading submissions for user " . strtoupper($member->username) . " in course " . $sectionInfo->section_title;
        $course->savePortfolio($files, $member, $assignments);
        $filesSaved = true;
        if ($course->download($section, $member))
            die(); // no more response, we're downloading a zip file
        break;  
    case 'find':
        $groups = $course->listAllGradingGroupMembers($section);
        $users = getAllUsers($groups);
        $find_user = function($v) use ($userid) { return strtoupper($v->username) == strtoupper($userid); };
        $user = current(array_filter($users, $find_user));
        echo $user->group;
        die();
        break;
    case 'csv':
    case 'excel':
        $groups = $course->listAllGradingGroupMembers($section);
        $users = getAllUsers($groups);
        header("Content-type: text/csv");
        header("Content-Disposition: attachment; filename=file.csv");
        header("Pragma: no-cache");
        header("Expires: 0");
        echo "\xEF\xBB\xBF";
        echo "uniqueid,username,group,first,last\n";
        foreach ($users as $user) {
            $out = $user->info->uid . ',' . $user->username . ',' . $user->group . ',' . $user->first_name . ',' . $user->last_name . "\n";
            if (strtolower($action) == 'excel')
                echo mb_convert_encoding($out, 'UTF-16LE', 'UTF-8');
            else
                echo $out;
        }
        die();
        break;  
    case 'clean':
        $course->purgeDownloads();
        break;
	case 'upload':
		$data = $course->uploadMembersCsv();
		$import = $course->importCsv($section, $data);
		break;
	case 'getid':
		try {
			header('Content-Type: application/json');   
			header('Access-Control-Allow-Origin: *');
			$member = $course->getUserInfoBySchoolId($userid);
		}
		catch(Exception $e) {
			http_response_code(500);
			die();
		}
		echo json_encode(['id'=>$member->id]);
		//echo $member->id;
	  die(); // ajax, no more output      	
  default:
        // nothing here
}

?>
