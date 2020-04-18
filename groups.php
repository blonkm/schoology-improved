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
require_once('helpers.php');
require_once('classes/FlxZipArchive.php');
require_once('classes/Course.php');
require_once('classes/Uploader.php');
require_once('classes/Timer.php');
require_once('classes/Cache.php');
require_once('classes/Filesystem.php');
require_once('classes/Submission.php');
require_once('classes/Html.php');
require_once('classes/Timetable.php');

debug(true);

$action = strtolower(filter_input(INPUT_GET, 'action', FILTER_SANITIZE_URL));
$section = strtolower(filter_input(INPUT_GET, 'section', FILTER_SANITIZE_URL));
$group = filter_input(INPUT_GET, 'group');
$assignment = strtolower(filter_input(INPUT_GET, 'assignment', FILTER_SANITIZE_URL));
$userid = strtolower(filter_input(INPUT_GET, 'userid', FILTER_SANITIZE_URL));
$cache = strtolower(filter_input(INPUT_GET, 'cache', FILTER_SANITIZE_URL));
$status = strtolower(filter_input(INPUT_GET, 'status', FILTER_SANITIZE_URL));
$category = strtolower(filter_input(INPUT_GET, 'category', FILTER_SANITIZE_URL));
$submission = strtolower(filter_input(INPUT_GET, 'submission', FILTER_SANITIZE_URL));
$file = strtolower(filter_input(INPUT_GET, 'file', FILTER_SANITIZE_URL));
$updateCount = strtolower(filter_input(INPUT_GET, 'n', FILTER_SANITIZE_URL)) ?: 1;


// init objects for entire page
$timer = new Timer;
$course = new Course();
if ($cache == 'no')
  $course->disableCache();

if (!empty($section)) {
//    die("error:missing parameter 'section'");
  $sectionInfo = $course->getSectionInfo($section);
  $pageTitle = "Schoology Groups - section: " . $sectionInfo->section_title;
}

$filesSaved = false;
$menuItems = [
    'courses' => ['courses', 'show a list of courses and their sections'],
    'groups' => ['pictures', 'show a list of pictures of students'],
    'list' => ['list', 'show a list of students'],
    'excel' => ['export', 'save student list as Excel file'],
//  'code' => ['code', 'generate PHP code for groups-import.php'],
    'matrix' => ['submissions', 'download assignment files'],
    'attendance' => ['attendance', 'display attendance last week'],
    '' => ['', ''],
    'reload' => ['reload', 'reload current page data from Schoology'],
    'refresh' => ['refresh', 'update oldest record in database from api'],
    'import' => ['import', 'upload and import csv file'],
    'clean' => ['clean', 'delete all temporary files from server'],
    'create' => ['create', 'create all groups. Careful!'],
    'delete' => ['delete', 'delete all groups. Careful!']
];

require_once('controller.php');
?>

<!DOCTYPE html>
<html>

    <head>
        <meta charset="UTF-8" />
        <title><?= $pageTitle ?></title>
        <link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
        <link rel="stylesheet" href="styles/styles.css">
        <link rel="stylesheet" href="styles/nav.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css">
        <link rel="stylesheet" href="styles/tablesort.css">
        <link href="styles/zoom.css" rel="stylesheet">
        <link href="styles/dropzone.css" type="text/css" rel="stylesheet" />
        <link href="styles/awesomplete/awesomplete.base.css" rel="stylesheet" type="text/css"/>
        <link href="styles/awesomplete/awesomplete.css" rel="stylesheet" type="text/css"/>
        <link href="styles/awesomplete/awesomplete.theme.css" rel="stylesheet" type="text/css"/>
        
        <!-- 2 -->
        <script src="scripts/dropzone.js"></script>
        <script src="scripts/main.js"></script>
        <script src='scripts/tablesort.min.js'></script>

        <script src="scripts/awesomplete/awesomplete.min.js"></script>
        
        <!-- Include sort types you need -->
        <script src='scripts/sorts/tablesort.number.min.js'></script>
        <script src='scripts/sorts/tablesort.date.min.js'></script>       
    </head>
    <body>
        <header>&nbsp;</header>
        <? if (!empty($section)) { ?>
          <img id="course-logo" src="<?= $sectionInfo->profile_url ?>" width="170"/>
        <? } ?>
        <h1>Schoology: <?= $action ?></h1>
        <? if (!empty($section)) { ?>
          <h2>Course/Section: <?= $sectionInfo->course_title ?>: <?= $sectionInfo->section_title ?> (<?= $sectionInfo->section_code ?>)</h2>
          <p><a href="http://app.schoology.com/course/<?= $sectionInfo->id ?>">view on schoology</a></p>
          <p><cite><?= $sectionInfo->description ?></cite></p>
        <? } ?>

        <section class="container">
            <? if (!empty($section)) { ?>
              <div class="left-half">
                  <nav>
                      <ul class="vertical-list">
                          <?
                          foreach ($menuItems as $link => $info) {
                            if ($link == '') {
                              ?><li><h3>Admin</h3><li><?
                            } else {
                              ?><li><a id="<?= $link ?>Button" class="button <?= isMenuActive($action, $link) ?>" href="groups.php?section=<?= $sectionInfo->id ?>&action=<?= $link ?>" title="<?= $info[1] ?>"><?= $info[0] ?></a></li><?
                                }
                              }
                              ?>
                      </ul>
                  </nav>
              </div>
            <? } ?>
            <div class="right-half">
                <?
                $action = strtolower($action);
                switch ($action) {
                  case 'courses':
                    ?>
              
                      <nav id="courses">
                        <label for="course">Search courses:</label>
                        <input placeholder="start typing a course name" class="awesomplete dropdown-input" type="text" name="course" id="course" list="course_list"><button class="dropdown-btn"><span class="caret"></span></button>
                        <datalist id="course_list">
                          <? foreach ($courses as $objCourse) { 
                               foreach ($objCourse->sections as $section) { ?>
                                  <option data-id="<?= $section->id?>" value="<?= $objCourse->title . " - " . $section->section_title?>"><?= $objCourse->title ?> - <?= $section->section_title ?></option>
                              <? } 
                          } ?>                                    
                        </datalist>
                        <button id="selectCourseButton">Go</button>
                      </nav>
                            <?
                            break;
                          case 'pictures':
                          case 'groups':
                          case 'members':
                          case 'list':
                          case 'attendance':
                            ?>
                    <table class="sortedTable">
                      <thead>
                        <tr>
                    <? if ($action == 'pictures' or $action == 'groups') { ?>
                              <th>Picture</th>
    <? } ?>
                            <th>Group</th>
                            <th>UserName</th>
                            <th>FirstName</th>
                            <th>LastName</th>
    <? if ($action == 'attendance') { ?>
                              <th>Login Date</th>
                              <th>Login Time</th>
                              <th>Week</th>
                              <th>Lesson</th>
    <? } ?>
                        </tr>
                      </thead>
                      <tbody>
    <?
    foreach ($users as $user) {
      $filter = 'none';
      if (isset($group))
        $filter = $user->group == $group;
      if ($filter == true or $filter == 'none') {
        ?>
                            <tr>
                            <? if ($action == 'pictures' or $action == 'groups') { ?>
                                  <td><img src="<?= $user->info->picture_url ?>" data-action="zoom" width="80" /></td>
                                <? } ?>
                                <td><?= $user->group ?></td>
                                <td><a href="groups.php?section=<?= $section ?>&userid=<?= $user->info->uid ?>&action=user"><?= $user->username ?></a></td>
                                <td><?= $user->first_name ?></td>
                                <td><?= $user->last_name ?></td>
        <? if ($action == 'attendance') { ?>
                                  <td><?= date(Course::DATE_FORMAT, $user->info->last_login) ?></td>
                                  <td><?= date(Course::TIME_FORMAT, $user->info->last_login) ?></td>
                                  <td><?= date('W', $user->info->last_login) ?></td>
                                  <td><?= $user->info->lesson ?></td>
        <? } ?>
                            </tr>
                              <? }
                            }
                            ?>
                      </tbody>
                    </table><?
                        break;
                      case 'matrix':
                        $c = new Course();
                        $categories = $c->getGradingCategories($section);
                        $html = new Html;
                        ?>
                    <nav role="search">
                        <form method="get" action="groups.php">
    <?= $html->getParamsAsHiddenInputs(['category', 'status']); ?>
                            <label for="status">Completion status</label>
                            <select id="status" name="status">
                                <option value="3">None</option>
                                <option value="0" <?= $html->selected('0', $status) ?>>Must view the item</option>
                                <option value="1" <?= $html->selected('1', $status) ?>>Must make a submission</option>
                                <option value="2" <?= $html->selected('2', $status) ?>>Must score at least 80</option>
                            </select>
                            <select id="category" name="category">
    <?
    foreach ($categories as $id => $categoryTitle) {
      ?><option value="<?= $id ?>" <?= $html->selected($id, $category) ?>><?= $categoryTitle ?></option><?
                                }
                                ?>
                            </select>
                            <button type="submit">Filter</button>
                        </form>
                    </nav>
                    <br/>
                    <table class="sortedTable" id="matrix">
                      <thead>
                        <tr>
                            <th>Group</th>
    <? foreach ($assignments as $assignment) { ?>
                              <th><?= $assignment ?></th>
                            <? } ?>
                      </thead>
                      <tbody>
                            <? foreach ($groups as $group => $groupId) { ?>
                          <tr>
                              <td><a href="groups.php?section=<?= $section ?>&action=groups&group=<?= $group ?>"><?= $group ?></a></td><? foreach ($assignments as $id => $assignment) { ?>
                                <td><a target="_blank" title="<?= $group ?> - <?= $assignment ?>" href="groups.php?section=<?= $section ?>&group=<?= urlencode($group) ?>&assignment=<?= $id ?>&action=files">&nbsp;</a></td>
                              <? } ?>
                          </tr>
                            <? } ?>
                      </tbody>
                    </table>
                        <?
                        break;
                      case 'save':
                      case 'files':
                        ?>
                    <h3 id="assignment-title">
                    <? foreach ($assignments as $id => $title) { ?>
                          <?= $id == $assignment ? $title : '' ?>
                        <? } ?>
                        for group <?= $group ?>
                    </h3>
                    <table class="sortedTable">
                      <thead>
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
                            <th><i class="fa fa-comment" aria-hidden="true"></i></th>
                        </tr>
                      </thead>
                      <tbody>
    <? foreach ($files as $file) { ?>
                          <tr>
                              <td><?= $file->group ?></td>
                              <td><a target="_blank" href="groups.php?section=<?= $section ?>&userid=<?= $file->member->uid ?>&action=user"><?= $file->userId ?></a></td>
                              <td><?= $file->first_name ?></td>
                              <td><?= $file->last_name ?></td>          
                              <td class="numeric"><?= $file->revision ?></td>          
                              <td class="numeric"><?= $file->size . ' MB' ?></td>          
                              <td><?= $file->datetime ?></td>          
                              <td class="numeric"><?= $file->grade ?></td>          
                              <td><a title="<?= $file->comment ?>" href="?section=<?= $section ?>&assignment=<?= $assignment ?>&group=<?= urlencode($group) ?>&userid=<?= $file->member->uid ?>&submission=<?= $file->id?>&action=file"><?= $file->name ?></a></td>
                              <td><i title="<?= $file->comment ?>" class="fa <?= $file->comment != '' ? 'fa-comment' : '' ?>" aria-hidden="true"></i></td>
                          </tr>
      <?
    }
    ?>
                      </tbody>
                    </table>
                    <p><a href="?section=<?= $section ?>&assignment=<?= $assignment ?>&group=<?= urlencode($group) ?>&action=download">download files</a> 
                        | 
                        <a href="?section=<?= $section ?>&assignment=<?= $assignment ?>&action=download">download files of all groups</a> 
                    </p><?
                    break;
                  case 'user':
                    ?><h3 id="user-title"><?= $first ?> <?= $last ?> (<?= $schoolId ?>)</h3>
                    <table class="sortedTable">
                      <thead>
                        <tr>
                            <th>Assignment</th>
                            <th>Revision</th>
                            <th>Size</th>
                            <th>Date</th>
                            <th>Grade</th>
                            <th>File</th>
                            <th><i class="fa fa-comment" aria-hidden="true"></i></th>
                        </tr>
                      </thead>
                      <tbody>
    <?
    $prevAssignmentId = null;
    foreach ($files as $file) {
      if ($file->assignmentId == $prevAssignmentId) {
        ?>
                            <tr>
                                <td colspan="5"></td>
                                <td><a href="<?= $file->url ?>"><?= $file->name ?></a></td>
                                <td></td>
                            </tr>
      <? } else { ?>
                            <tr>
                                <td><?= $assignments[$file->assignmentId] ?></td>
                                <td class="numeric"><?= $file->revision ?></td>          
                                <td class="numeric"><?= $file->size . ' MB' ?></td>          
                                <td><?= $file->datetime ?></td>          
                                <td class="numeric"><?= $file->grade ?></td>                      
                                <td><a href="<?= $file->url ?>"><?= $file->name ?></a></td>
                                <td><i title="<?= $file->comment ?>" class="fa <?= $file->comment != '' ? 'fa-comment' : '' ?>" aria-hidden="true"></i></td>
                            </tr>
      <?
      }
      $prevAssignmentId = $file->assignmentId;
      ?>
                          <?
                        }
                        ?>
                      </tbody>
                    </table>
                    <p><a href="?section=<?= $section ?>&userid=<?= $userid ?>&action=portfolio">download files</a></p>
                    <?
                    break;
                  case 'code':
                    ?>
                    <h3>PHP code</h3>
                    <p>Place this code in the file groups-import.php</p>
                    <code>
                        <?
                        foreach ($groups as $group) {
                          foreach ($group->members as $member)
                            echo '$groups[' . sq($group->title) . '][] = ' . sq($member->uid) . ';<br/>';
                        }
                        ?></code><?
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
                        <?= $source ?>
                    </div>
                    <?
                    break;
                  case 'reload':
                    break;
                  case 'refresh':
                    ?>
                    <p>The following records have been retrieved from Schoology:</p>
                    <ul>
                    <?
                      foreach ($updatedRecords as $url => $resource) {
                        ?><li><?=$url?></li><?
                      }
                    ?></ul><?
                    break;
                  case 'create':
                    echo "groups created";
                    break;
                  case 'delete':
                    echo "groups deleted";
                    break;
                  case 'import':
                    if (isset($_FILE)) {
                      echo 'upload';
                    } else {
                      ?>            
                      <h3>Import members</h3>
                      
                      <form id="frmImport" name="frmImport" action="groups.php?section=<?= $section ?>&action=upload" class="dropzone">
                        <div class="fallback">
                              <input type="file" name="upload" accept=".csv" />
                          </div>
                      </form>

                      <div id="preview"></div>
                      <p>Make sure that</p>
                      <ul>
                          <li>Members are registered in Schoology</li>
                          <li>Your file is a CSV file, formatted as
                              <ol>
                                  <li>uniqueid</li>
                                  <li>user name</li>
                                  <li>group name</li>
                                  <li>first  name (optional)</li>
                                  <li>last name</li>
                              </ol>
                          </li>
                      </ul>

      <?
    }
}
$course->showErrors();
?>
                <p class="elapsed">Page generated in <?= $timer->getElapsedTime() ?> seconds using <?= $course->apiCounter() ?> API calls.</p>

            </div>
        </section>
        <script src="scripts/zoom-vanilla.js"></script>
        <script>

        </script>         
    </body>
</html>
