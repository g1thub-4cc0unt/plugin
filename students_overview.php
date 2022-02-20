<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * @package   local_analytics
 * @copyright 2020, You Name <your@email.address>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__. "/../../config.php");
require_once(__DIR__. "/layout.php");

global $PAGE, $CFG, $DB;
$PAGE->set_url(new moodle_url("/local/analytics/students_overview.php"));


//read Note from DB
function getNote($noteid) {
    global $DB;
    $sql = "SELECT *
            FROM {local_analytics_notes}
            WHERE id = ?";
    $note = $DB->get_record_sql($sql, [$noteid]);
    return $note;
}

//read Student Object with AvgGrade, Material viewed, Forum Read, Last Access
function getStudent($userID, $courseID) {
    $student = new stdClass();
    $student -> id = $userID;
    $student -> avgQGrade = getAvgQGrade($userID, $courseID);
    $student -> avgAGrade = getAvgAGrade($userID, $courseID);
    $student -> lastAccess = getLastAccess($userID,$courseID);
    $student -> materialViewed = getResourcesViewed($userID,$courseID);
    $student -> postsRead = getForumRead($userID,$courseID);
    $student -> postsCreated = getPostsCreated($userID,$courseID);
    $student -> quizzesTaken = getQuizzesTaken($userID,$courseID);
    $student -> assignmentsTaken = getAssignmentsTaken($userID,$courseID);

    return $student;
}

function getAvgQGrade($userID, $courseID){
    global $DB;
    $sql = "SELECT avg(g.grade) AS avggrade
            FROM {quiz} q
            JOIN {quiz_grades} g ON q.id = g.quiz
            WHERE q.course = ? AND g.userid = ?";
    $grade = $DB->get_record_sql($sql, [$courseID,$userID]);
    return $grade -> avggrade;
}

function getAvgAGrade($userID, $courseID){
    global $DB;
    $sql = "SELECT avg(g.grade) AS avggrade
    FROM {assign} a
    JOIN {assign_grades} g ON a.id = g.assignment
    WHERE a.course = ?  AND g.userid = ?
    AND g.grade IS NOT NULL AND g.grade <> -1";

    $grade = $DB->get_record_sql($sql, [$courseID,$userID]);
    return $grade -> avggrade;
}

function getLastAccess($userID, $courseID){
    global $DB;
    $sql = "SELECT timecreated
            FROM {logstore_standard_log}
            WHERE action ='viewed' AND target='course'
            AND courseid = ? AND userid = ? 
            ORDER BY id DESC LIMIT 1";
    $lastAccess = $DB->get_record_sql($sql, [$courseID,$userID]);
    return $lastAccess -> timecreated;
}

function getResourcesViewed($userID,$courseID){
    global $DB;
    $sql = "SELECT * 
            FROM {resource} 
            WHERE course = ?";
    $resources = $DB->get_records_sql($sql, [$courseID]);
    $amountResources = count($resources);
    if ($amountResources == 0) {
        return null;
    }
    $resourcesViewed = 0;

    foreach ($resources as $resource){
        $sql = "SELECT count(*) as amountviewed
        FROM {logstore_standard_log} 
        WHERE courseid = ? AND userid = ?
        AND action='viewed' AND component = 'mod_resource' AND target = 'course_module' AND objectid = ?";
        $resourceViewed = $DB->get_record_sql($sql, [$courseID,$userID,$resource->id]);
        if (($resourceViewed->amountviewed) > 0){
            $resourcesViewed += 1;
        }
    }
    return (($resourcesViewed/$amountResources)*100);
}

function getForumRead($userID,$courseID){
    global $DB;
    $sql = "SELECT count(*) AS amountposts
            FROM {forum_posts} p
            JOIN {forum_discussions} d ON d.id = p.discussion
            WHERE d.course = ?";
    $amountPosts = $DB->get_record_sql($sql, [$courseID]);
    $amountPosts = $amountPosts -> amountposts;


    $sql = "SELECT count(*) AS amountpostsread
            FROM {forum_read} r
            JOIN {forum_discussions} d ON d.id = r.discussionid
            WHERE d.course = ? AND r.userid = ?";
    $amountPostsRead = $DB->get_record_sql($sql, [$courseID, $userID]);
    $amountPostsRead = $amountPostsRead -> amountpostsread;

    return (($amountPostsRead/$amountPosts)*100);
}

function getPostsCreated($userID,$courseID){
    global $DB;
    $sql = "SELECT count(*) AS amountposts
            FROM {forum_posts} p
            JOIN {forum_discussions} d ON d.id = p.discussion
            WHERE d.course = ? and p.userid = ?";
    $amountPosts = $DB->get_record_sql($sql, [$courseID, $userID]);
    return $amountPosts -> amountposts;
}

function getQuizzesTaken($userID,$courseID){
    global $DB;
    $sql = "SELECT DISTINCT q.id
        FROM {quiz} q
        JOIN {quiz_attempts} a ON q.id = a.quiz
        WHERE q.course = ? AND a.state = 'finished' AND a.userid = ?";
    $quizzes = $DB->get_records_sql($sql, [$courseID,$userID]);
    return count($quizzes);
}

function getAssignmentsTaken($userID,$courseID){
    global $DB;

    $sql = "SELECT DISTINCT a.id
    FROM {assign} a
    JOIN {assign_submission} s ON a.id = s.assignment
    WHERE a.course = ? AND s.status = 'submitted' AND s.userid = ? ";

    $assignments = $DB->get_records_sql($sql, [$courseID,$userID]);
    return count($assignments);
}

?>


    <!-- Layout -->
    <head>
        <title>Students</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <style>
            span.blueColor {
                color:#004C93;
                font-family:Arial;
                font-size: 20px;
                font-weight: bold;
            }
            blueColor {
                color:#004C93;
                font-family:Arial;
            }
            span.blackColor {
                color: black;
                font-weight: bold;
                font-family: Arial;
            }

            <!-- "W3Schools" -->
            * {
                box-sizing: border-box;
            }

            /* Style the search field */
            form.example input[type=text] {
                padding: 10px;
                font-size: 17px;
                border: 1px;
                float: left;
                width: 80%;
                background: white;
                margin-top: auto;
                margin-bottom: auto;
                height: 100%;
            }

            /* Style the submit button */
            form.example button {
                height: 100%;
                float: left;
                width: 20%;
                padding: 10px;
                background: white;
                color: white;
                font-size: 17px;
                border: 1px ;
                border-left: none; /* Prevent double borders */
                cursor: pointer;
                margin-top: auto;
                margin-bottom: auto;
            }

            /* Clear floats */
            form.example::after {
                content: "";
                clear: both;
                display: table;
            }
        </style>
    </head>




<?php

//Read Course Information
$course_id = required_param("courseid", PARAM_INT);
$course = getCourseInfo($course_id);

$startDate = $course -> startDate;
$startDateEpoch = $course -> startDateEpoch;
$endDateEpoch = $course -> endDateEpoch;
$courseId = $course -> id;
$course_name = $course -> name;

//Read Number of enrolled Students in Course
// SQL Source: https://moodle.org/mod/forum/discuss.php?d=118532#p968575 with slight modifications
$sql = 'SELECT u.id as uid, u.firstname, u.lastname 
    FROM {course} c
    JOIN {context} ct ON c.id = ct.instanceid
    JOIN {role_assignments} ra ON ra.contextid = ct.id
    JOIN {user} u ON u.id = ra.userid
    JOIN {role} r ON r.id = ra.roleid
    WHERE ra.roleid = 5 AND c.id = ?';
$records = $DB->get_records_sql($sql, [$courseId]);

//UserID String
$userIDs = array();
foreach ($records as $user){
    $userIDs[] = $user->uid;
}
$userIDString = join("','",$userIDs);

$studentSearch = $records;


//Search BY Name
if (isset($_POST["sname"]) AND $_POST["sname"] != ""){

    $studentSearch = array();
    $name = explode(" ",$_POST["sname"]);

    if(array_key_exists(1,$name) ) {
        foreach ($records as $user) {
            if ((($user->firstname) == $name[0]) AND (($user->lastname) == $name[1])) {
                $studentSearch[] = $user;
            }
        }
    }else{
        foreach ($records as $user) {
            if ((($user->firstname) == $name[0]) or (($user->lastname) == $name[0])) {
                $studentSearch[] = $user;
            }
        }
    }
}

$filterNumber = null;
$setFilter = 0;
$filter = null;
//Filter By Quizzes Taken
if (isset($_POST["sfilter"]) AND $_POST["sfilter"] != ""){
    $parts = explode(":",$_POST["sfilter"]);

    if (strtoupper($parts[0]) == strtoupper("Quizzes Taken")){
        $setFilter = 1;
        $filter = trim($parts[1], " ");
        $filterNumber = $parts[2];
    }

}



//Needed in Search HTML
$analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/students_overview.php");
$url = $analytics_url . "?courseid=". $course_id;


if(True){}

elseif (isset($_POST['scontext'])){
    $sql ="SELECT *
       FROM {local_analytics_notes}
       WHERE courseid = ? AND (context LIKE ?)";
    $notes = $DB->get_records_sql($sql,[$courseId,$_POST["scontext"]."%"]);
}else{
    $sql ="SELECT *
       FROM {local_analytics_notes}
       WHERE courseid = ?";
    $notes = $DB->get_records_sql($sql,[$courseId]);
}

$students = array();
foreach($studentSearch as $user){
    $student = getStudent($user -> uid,$courseId);
    $student -> firstname = $user ->firstname;
    $student -> lastname = $user ->lastname;
    $students[] = $student;
}

//Tag Student
if (isset($_GET['tag']) AND ($_GET['tag'] == "true")) {
    //Check if Student is part of DB
    $studentId = $_GET['uid'];
    $sql = "SELECT count(*) AS amount
            FROM {local_analytics_tagged}
            WHERE userid = ? AND courseid = ?";
    $tagged = $DB->get_record_sql($sql, [$studentId, $courseId]);

    if (($tagged->amount) > 0){
        $DB->delete_records_select("local_analytics_tagged", 'userid= ?',[$studentId]);
    }else {
        //Insert Student into Tagged Database
        foreach ($students as $student){
            if ($student -> id == $studentId){
                break;
            }
        }
        $record = new stdClass();
        $record->courseid = $courseId;
        $record->userid = $student->id;
        $record->name = ($student->firstname) . " " . ($student->lastname);
        $DB->insert_record("local_analytics_tagged", $record);
    }
}

//Bookmarked Students
$bookmarkedStudents = array();

$sql = "SELECT userid
        FROM {local_analytics_tagged}
        WHERE courseid = ?";

$bookmarks = $DB->get_records_sql($sql, [$courseId]);
foreach ($bookmarks as $bookmark){
    $bookmarkedStudents[] = $bookmark -> userid;
}



//Students Order by
if (isset($_GET['sort']) AND (isset($_GET['asc']))) {
    //Name Ascending
    if (($_GET['sort'] == "name") AND ($_GET['asc']) == "true") {
        usort($students, function($a, $b) {return strcmp($a->lastname, $b->lastname);});
        }
    //Name Descending
    elseif(($_GET['sort'] == "name") AND ($_GET['asc']) == "false"){
        usort($students, function ($a, $b) {
            return strcmp($a->lastname, $b->lastname);});
        $students = array_reverse($students);
        }
    //Quizzes Taken Ascending
    elseif(($_GET['sort'] == "qt") AND ($_GET['asc']) == "true"){
        usort($students, function ($a, $b) {
            return ($a->quizzesTaken >= $b->quizzesTaken);});
    }
    //Quizzes Taken Descending
    elseif(($_GET['sort'] == "qt") AND ($_GET['asc']) == "false"){
        usort($students, function ($a, $b) {
            return ($a->quizzesTaken <= $b->quizzesTaken);});
    }
    //Assignments Taken Ascending
    elseif(($_GET['sort'] == "at") AND ($_GET['asc']) == "true"){
        usort($students, function ($a, $b) {
            return ($a->assignmentsTaken >= $b->assignmentsTaken);});
    }
    //Assignments Taken Descending
    elseif(($_GET['sort'] == "at") AND ($_GET['asc']) == "false"){
        usort($students, function ($a, $b) {
            return ($a->assignmentsTaken <= $b->assignmentsTaken);});
    }
    //Material Viewed Ascending
    elseif(($_GET['sort'] == "mv") AND ($_GET['asc']) == "true"){
        usort($students, function ($a, $b) {
            return ($a->materialViewed >= $b->materialViewed);});
    }
    //Material Viewed Descending
    elseif(($_GET['sort'] == "mv") AND ($_GET['asc']) == "false"){
        usort($students, function ($a, $b) {
            return ($a->materialViewed <= $b->materialViewed);});
    }
    //Posts Read Ascending
    elseif(($_GET['sort'] == "pr") AND ($_GET['asc']) == "true"){
        usort($students, function ($a, $b) {
            return ($a->postsRead >= $b->postsRead);});
    }
    //Posts Read Descending
    elseif(($_GET['sort'] == "pr") AND ($_GET['asc']) == "false"){
        usort($students, function ($a, $b) {
            return ($a->postsRead <= $b->postsRead);});
    }
    //Last Access Ascending
    elseif(($_GET['sort'] == "la") AND ($_GET['asc']) == "true"){
        usort($students, function ($a, $b) {
            return ($a->lastAccess >= $b->lastAccess);});
    }
    //Last Access Descending
    elseif(($_GET['sort'] == "la") AND ($_GET['asc']) == "false"){
        usort($students, function ($a, $b) {
            return ($a->lastAccess <= $b->lastAccess);});
    }
}


$urlBookmarking = new moodle_url($CFG->wwwroot."/local/analytics/");

?>
    <!-- Heading View -->
    <div style="position: fixed;top: 70px; left:125px; font-family: Arial; z-index: 4">
        <h2>Students</h2>
    </div>

    <!-- Search Bars -->

    <div style="top: 9%; right: 25%; position: absolute; height: 7%; width: 50%; background-color:#3B3C3B ">
        <div style="font-family:Arial;  display: flex;  justify-content:space-around; align-items: center">
            <!-- Search by Name -->
            <div>
                <form class="example" action="<?php echo $url ?>" method="post" style="max-width:300px">
                    <input type="text" placeholder='Search By Name' name="sname">
                    <button>&#128269; </button>
                </form>
            </div>

            <!-- Search by Context -->
            <div>
                <form class="example" action="<?php echo $url ?>" method="post" style="max-width:300px">
                    <input type="text" placeholder='Filter "Quizzes Taken: <= :2"' name="sfilter">
                    <button>&#128269; </button>
                </form>
            </div>
        </div>
    </div>




<?php
$urlSort = new moodle_url($CFG->wwwroot."/local/analytics/students_overview.php?courseid=".$course_id);
?>
    <div>
        <div style="overflow-y: scroll; width:70%; height:75%; top:25%; right: 15% ;position: absolute ">

            <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                <div style="width:60px;word-wrap:break-word;"class="blueColor"></div>
                <div style="width:15%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Student <br> Name</span>

                    <!-- Order by Name -->
                    <div>

                    <a href="<?php echo $urlSort."&sort=name&asc=true"?>">
                        <div>
                            <img src="./icons/arrow_up.png" width="20px" height="20px">
                        </div>
                    </a>

                    <a href="<?php echo $urlSort."&sort=name&asc=false"?>">
                        <div>
                            <img src="./icons/arrow_down.png" width="20px" height="20px">
                        </div>
                    </a>
                    </div>
                </div>
                <div style="width:15%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Quizzes <br> Taken</span>

                    <!-- Order by Name -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=qt&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=qt&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
                <div style="width:15%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Assignments <br> Taken</span>

                    <!-- Order by Name -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=at&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=at&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
                <div style="width:15%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Material <br> Viewed</span>

                    <!-- Order by Name -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=mv&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=mv&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
                <div style="width:15%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Posts <br> Read / Created</span>

                    <!-- Order by Name -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=pr&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=pr&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
                <div style="width:10%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Last <br> Access</span>

                    <!-- Order by Name -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=la&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=la&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
            </div>


            <br>
            <hr>
            <br>

            <!-- List Students -->

            <?php
                $analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/student_analytics.php");
                $url = $analytics_url . "?courseid=". $course_id;
                foreach($students as $student) {
                    if (!(($setFilter == 1) AND !version_compare($student->quizzesTaken, $filterNumber,$filter))){
                    } else{
                        continue;
                    }
            ?>



                <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                    <div style="width:60px;word-wrap:break-word; text-align: right;">
                        <a href="<?php echo $urlBookmarking."students_overview.php?courseid=".$course_id."&tag=true&uid=".$student->id ?>">
                            <div class="tooltip">
                                <img src=<?php if (in_array($student->id, $bookmarkedStudents)){
                                    echo "./icons/google/outline_bookmark_black_48dp.png";
                                }
                                else{
                                    echo "./icons/google/outline_bookmark_border_black_48dp.png";
                                }?> width="24px" height="24px">
                                <span class="tooltiptext">Bookmark Student</span>
                            </div>
                        </a>
                    </div>
                    <div style="width:15%;word-wrap:break-word;">

                        <a href="<?php echo $url ?>&userid=<?php echo $student->id ?>">
                            <?php echo ($student->lastname).", ". ($student->firstname)  ?>
                        </a>
                    </div>

                    <div style="width:15%;word-wrap:break-word;">
                        <?php echo round($student->quizzesTaken,2) ?>
                    </div>

                    <div style="width:15%;word-wrap:break-word;">
                        <?php echo round($student->assignmentsTaken,2) ?>
                    </div>

                    <div style="width:15%;word-wrap:break-word;">
                        <?php echo round($student->materialViewed,2)."%" ?>
                    </div>

                    <div style="width:15%;word-wrap:break-word;">
                        <?php echo round($student->postsRead,2)."% / ".$student->postsCreated?>
                    </div>

                    <div style="width:10%;word-wrap:break-word;">
                        <?php if (is_null($student->lastAccess)){
                            echo "-";
                        }else {
                            echo date('d/m/Y H:i:s', $student->lastAccess);
                        }?>
                    </div>
                </div>
                <br>
                <hr>
                <br>
            <?php } ?>
        </div>
    </div>


    </body>
<?php
