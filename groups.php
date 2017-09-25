<?php
/**
* (c) 2017 Michiel van der Blonk
*
* This Source Code Form is subject to the terms of the Mozilla Public
* License, v. 2.0. If a copy of the MPL was not distributed with this file,
* You can obtain one at http://mozilla.org/MPL/2.0/.
*
* See API docs: https://developers.schoology.com/api-documentation/rest-api-v1/grading-groups
*/
 
// Replace this with the local path to the Schoology PHP SDK
// which should contain your API keys
require_once('sdk/SchoologyApi.class.php');
require_once('classes/FlxZipArchive.php');
require_once('classes/Course.php');

// just for debugging
function dump($s) {
    echo "<pre>";
    var_dump($s);
    echo "</pre>";
}

$course = new Course();
$action = strtolower(filter_input( INPUT_GET, 'action', FILTER_SANITIZE_URL ));
$section = strtolower(filter_input( INPUT_GET, 'section', FILTER_SANITIZE_URL ));
//$group = strtoupper(filter_input( INPUT_GET, 'group', FILTER_SANITIZE_URL ));
$group = filter_input( INPUT_GET, 'group');
$assignment = strtolower(filter_input( INPUT_GET, 'assignment', FILTER_SANITIZE_URL ));
$userid = strtolower(filter_input( INPUT_GET, 'userid', FILTER_SANITIZE_URL ));
if (empty($section))
    die("error:missing parameter 'section'");
    
$sectionInfo = $course->getSectionInfo($section);
$pageTitle = "Schoology Groups - section: " . $sectionInfo->section_title;
$filesSaved = false;

require_once('controller.php');
?>

<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <title><?=$pageTitle?></title>
    <link rel="shortcut icon" href="https://app.schoology.com/sites/all/themes/schoology_theme/favicon.ico" type="image/x-icon" />
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header>&nbsp;</header>
    <img id="course-logo" src="<?=$sectionInfo->profile_url?>" width="170"/>
    <h1>Schoology Group Members</h1>
    <h2>Course/Section: <?=$sectionInfo->course_title?>: <?=$sectionInfo->section_title?> (<?=$sectionInfo->section_code?>)</h2>
    <p><a href="http://app.schoology.com/course/<?=$sectionInfo->id?>">view on schoology</a></p>
    <p><cite><?=$sectionInfo->description?></cite></p>
    <?
    $action = strtolower($action);
    switch($action) {
		case 'pictures':
        case 'groups':
        case 'members':
        case 'list'
            ?>
            <table>
            <tr>
                <?if ($action=='pictures' or $action=='groups') {?>
                <th>Picture</th>
                <?}?>
                <th>Group</th>
                <th>UserName</th>
                <th>FirstName</th>
                <th>LastName</th>
            </tr>
            <?
            foreach ($users as $user) { 
				$filter = 'none';
				if (isset($group))
					$filter = $user->group == $group;
                if ($filter==true or $filter=='none') { ?>
                <tr>
					<?if ($action=='pictures' or $action=='groups'){ ?>
                    <td><img src="<?=$user->info->picture_url?>" width="80" /></td>
                    <?}?>
                    <td><?=$user->group?></td>
                    <td><a href="/schoology/groups.php?section=<?=$section?>&userid=<?=$user->info->uid?>&action=user"><?=$user->username?></a></td>
                    <td><?=$user->first_name?></td>
                    <td><?=$user->last_name?></td>
                </tr>
                <?}
            }?>
            </table><?
            break;  
        case 'matrix':
            ?>
            <table id="matrix">
            <tr>
                <th>Group</th>
            <?foreach ($assignments as $assignment) {?>
                <th><?=$assignment?></th>
            <?}?>
            <?foreach ($groups as $group=>$groupId) { ?>
                    <tr>
                        <td><?=$group?></td><?
                    foreach ($assignments as $id=>$assignment) { ?>
                        <td><a target="_blank" title="<?=$group?> - <?=$assignment?>" href="groups.php?section=<?=$section?>&group=<?=$group?>&assignment=<?=$id?>&action=files">&nbsp;</a></td>
                    <?}?>
                    </tr>
            <?}?>
            </table>
            <?
            break;
        case 'save':
        case 'files':?>
            <h3 id="assignment-title">
            <?foreach ($assignments as $id=>$title) {?>
                <?=$id==$assignment?$title:''?>
            <?}?>
            for group <?=$group?>
            </h3>
            <table>
            <tr>
                <th>Group</th>
                <th>Username</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Revision</th>
                <th>Size</th>
                <th>Date</th>
                <th>Grade</th>
                <th>File</th>
            </tr>
            <?
                foreach ($files as $file) { ?>
                <tr>
                    <td><?=$file->group?></td>
                    <td><a target="_blank" href="/schoology/groups.php?section=<?=$section?>&userid=<?=$file->member->uid?>&action=user"><?=$file->userId?></a></td>
                    <td><?=$file->first_name?></td>
                    <td><?=$file->last_name?></td>          
                    <td class="numeric"><?=$file->revision?></td>          
                    <td class="numeric"><?=$file->size . ' MB'?></td>          
                    <td><?=$file->datetime?></td>          
                    <td class="numeric"><?=$file->grade?></td>          
                    <td><a href="<?=$file->url?>"><?=$file->name?></a></td>
                </tr>
            <?
            }
            ?></table>
                <p><a href="?section=<?=$section?>&assignment=<?=$assignment?>&group=<?=$group?>&action=download">download files</a></p>
                <p><a href="?section=<?=$section?>&assignment=<?=$assignment?>&action=download">download files of all groups</a></p><?
            break;
        case 'user':
            ?><h3 id="user-title"><?=$first?> <?=$last?> (<?=$schoolId?>)</h3>
            <table>
            <tr>
                <th>Assignment</th>
                <th>Revision</th>
                <th>Size</th>
                <th>Grade</th>
                <th>File</th>
            </tr>
            <?
                foreach ($files as $file) { ?>
                <tr>
                    <td><?=$assignments[$file->assignmentId]?></td>
                    <td class="numeric"><?=$file->revision?></td>          
                    <td class="numeric"><?=$file->size . ' MB'?></td>          
                    <td class="numeric"><?=$file->grade?></td>                      
                    <td><a href="<?=$file->url?>"><?=$file->name?></a></td>
                </tr>
            <?
            }
            ?></table>
                <p><a href="?section=<?=$section?>&userid=<?=$userid?>&action=portfolio">download files</a></p>
            <?
            break;
        case 'code':
            echo "<h3>Excel code</h3>";
            foreach ($groups as $group) {
                echo "<br/>" . $group->title . "=";
                $ids = [];
                foreach ($group->members as $member) 
                    $ids[] = $member->uid;
                echo join($ids, ", ");      
            }
            break;
        case 'source':
            $source = '';
            echo "<h3>Source code</h3>";
            foreach ($groups as $group) {
                foreach ($group->members as $member) 
                    $source .= "\$groups['" . $group->title . "'][] = '" . $member->uid . "'; // " . "\t" . $member->name_last . "\t" . $member->name_first . "<br/>\n";
            }
            ?>
            <div class="source">
                <?=$source?>
            </div>
            <?
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
