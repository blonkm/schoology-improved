<?php

/**
* (c) Michiel van der Blonk
*
* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this file,
* You can obtain one at http://mozilla.org/MPL/2.0/.
*
* See API docs: https://developers.schoology.com/api-documentation/rest-api-v1/grading-groups
*/
 
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Replace this with the path to the Schoology PHP SDK
require_once('sdk/SchoologyApi.class.php');

function dump($s) {
    echo "<pre>";
    var_dump($s);
    echo "</pre>";
}

/** 
 *  schoology API calls to make groups
 *  or query groups and group members
 */
class Course {
    const API_KEY = 'YOUR API KEY HERE';
    const API_SECRET = 'YOUR API SECRET HERE';

    private $_app;
	private $_errors;
	
    /** singleton for the schoology app object
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

	function addError($message) {		
		$this->_errors[] = $message;
	}

	function showErrors() {		
		foreach ($this->_errors as $message)
			echo '<p class="error">' . $message . '</p>';
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
     *  this is a one time thing, for the 2016 course
     *  IDs are students schoology IDs
     *  the api uses enrollment ids
     *  this is generated in an Excel File 
     */    
    function groupUsers() {
        // set groups
        $groupsAsText = array();
		/* TODO:
		 * create an entry for each group you wish to make 
		 * using the schoology ids of users
		 * you can find those in their profile page url
		 * or if you are an administrator you can export a member list
		*/
		$groupsAsText['GROUP_NAME_HERE']	=	'1, 2, 3, 4, 5'; //user list with example users 1,2,3,4 and 5 

		$groups=[];
		foreach ($groupsAsText as $key=>$group) {
			$groups[$key] = preg_split('/, /', $group);
		}
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
                else
                	$this->addError("user not in group: ${user}->first_name ${user}->last_name ( ${user}->uid )<br/>");
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

    // get enrollment ids for the members in a section
    function listMembers($sectionId) {
        $schoology = $this->app();
        $url = 'sections/{section_id}/enrollments?limit=200';
        $url = str_replace('{section_id}', $sectionId, $url);

        // do the call
        $response = $schoology->api($url, 'GET');
        $members = $response->result;

        foreach($members->enrollment as $enrollment) {
            $userList[$enrollment->uid] = $enrollment->id;
        }

        return $userList;
    }

    /** 
     *  Create grading groups
     *  
     *  @param sectionId the section in which we should make groups
     *  @caveats does not work yet. See 
    // see https://support.schoology.com/hc/en-us/requests/101876
     */    
    function createGradingGroups($sectionId) {
        $schoology = $this->app();
        
        // convert user ids to enrollment ids
        $lookup = $this->listMembers($sectionId);

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

	function deleteAllGroups($sectionId) {
        $schoology = $this->app();
        
        // convert user ids to enrollment ids
        $lookup = $this->listMembers($sectionId);
        $groups = $this->groups($lookup);
        
        $gradingGroups = $this->listGradingGroups($sectionId);

        foreach ($groups as $group) {
            $groupId = $gradingGroups[$group->title];
            $url = 'sections/{section_id}/grading_groups/{gg_id}';
            $url = str_replace('{section_id}', $sectionId, $url);
            $url = str_replace('{gg_id}', $groupId, $url);  
            // do the call
            $response = $schoology->api($url, 'DELETE');
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

        foreach($response->result->grading_groups as $group) {
            $groupList[$group->title] = $group->id;
        }
        return $groupList;
    }

    // make an array of grading groups
    // with group names as keys
    // and members as sub array
    function listGradingGroupMembers($sectionId) {
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

$course = new Course();

$action = strtolower(filter_input( INPUT_GET, 'action', FILTER_SANITIZE_URL ));
$section = strtolower(filter_input( INPUT_GET, 'section', FILTER_SANITIZE_URL ));

if (empty($section))
    die("error:missing parameter 'section'");
    
$sectionInfo = $course->getSectionInfo($section);
switch (strtolower($action)) {
    case 'members':
        $members = $course->listMembers($section); 
        dump($members);
        die();
        break;
    case 'groups':
    case 'list':
    case 'code':
    case '':
        $groups = $course->listGradingGroupMembers($section);
        $users = [];
        foreach ($groups as $group) 
            foreach ($group->members as $member) 
                $users[] = (object)([
                    'group'=>$group->title, 
                    'first_name'=>$member->name_first, 
                    'last_name'=>$member->name_last,
                    'username'=>strtoupper($member->username),
                    'info'=>$member]);
        break;
    case 'create':
        $course->createGradingGroups($section);
        $course->addMembersToGradingGroups($section); 
        break;
    case 'delete':
    	$course->deleteAllGroups($section);
		break;
    default:
}

?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />

    <title>Schoology Group Members</title>

    <style>
        body { font: .76em arial; margin:1em; }
        h1,h2,h3 {font-size:1.2em; color: teal}
        h1 {font-size:1.6em}
        td, th {border:1px solid silver; padding:.2em}
        table {border-collapse:collapse}
        tr:nth-child(odd) { background-color: #e2f7f7;}
        p {font-size:1em}
        #admin p {font-size:1.2em}
    </style>
</head>
<body>

<h1>Schoology Group Members</h1>
<h2><img src="<?=$sectionInfo->profile_url?>" width="40"/> Course/Section: <?=$sectionInfo->course_title?>: <?=$sectionInfo->section_title?> (<?=$sectionInfo->section_code?>) <a href="http://app.schoology.com/course/<?=$sectionInfo->id?>">link</a></h2>

<p><cite><?=$sectionInfo->description?></cite></p>

<?
switch($action) {
	case 'groups':
		?>
		<table>
		<tr>
			<th>Picture</th>
			<th>Group</th>
			<th>UserName</th>
			<th>FirstName</th>
			<th>LastName</th>
		</tr>
		<?
		foreach ($users as $user) { ?>
			<tr>
				<td><img src="<?=$user->info->picture_url?>" width="80" /></td>
				<td><?=$user->group?></td>
				<td><?=$user->username?></td>
				<td><?=$user->first_name?></td>
				<td><?=$user->last_name?></td>
			</tr>
		<?}?>
		</table><?
		break;	
	case 'members':
		break;
	case 'list':
		?>
		<table>
		<tr>
			<th>Group</th>
			<th>ID</th>
			<th>Username</th>
			<th>First Name</th>
			<th>Last Name</th>
		</tr>
		<?
		foreach ($users as $user) { ?>
			<tr>
				<td><?=$user->group?></td>
				<td><?=$user->info->uid?></td>
				<td><?=$user->username?></td>
				<td><?=$user->first_name?></td>
				<td><?=$user->last_name?></td>		    
			</tr>
		<?}?>
		</table><?
		break;		
	case 'code':
        foreach ($groups as $group) {
        	echo "<br/>" . $group->title . "=";
        	$ids = [];
            foreach ($group->members as $member) 
            	$ids[] = $member->uid;
			echo join($ids, ", ");		
		}
		break;
	case 'create':
		echo "groups created";
		break;
	case 'delete':
		echo "groups deleted";
		break;
}		

$course->showErrors();
?>
</body>
</html>
