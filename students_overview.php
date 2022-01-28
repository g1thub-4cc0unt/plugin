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

require_once(__DIR__. "/graph-php-main/graph-php.class.php");
require_once(__DIR__. "/../../config.php");
require_once(__DIR__. "/layout.php");

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
    $student -> forumRead = getForumRead($userID,$courseID);

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
            AND d.course = ?";
    $amountPosts = $DB->get_record_sql($sql, [$courseID]);
    $amountPosts = $amountPosts -> amountposts;


    $sql = "SELECT count(*) AS amountpostsread
            FROM {forum_read} r
            JOIN {forum_discussions} d ON d.id = r.discussionid
            AND d.course = ? AND r.userid = ?";
    $amountPostsRead = $DB->get_record_sql($sql, [$courseID, $userID]);
    $amountPostsRead = $amountPostsRead -> amountpostsread;

    return (($amountPostsRead/$amountPosts)*100);
}
?>


    <!-- Layout -->
    <head>
        <title>Home</title>
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
            .center {
                margin: 0;
                position: absolute;
                top: 50%;
                left: 50%;
                -ms-transform: translate(-50%, -50%);
                transform: translate(-50%, -50%);
            }
            .divAlign{
                position:absolute;
                width:45px;
                height:45px;
                left:15px;
                border-radius: 15px;
            }
            .button {
                background-color: #007FFF;
                border: none;
                color: white;
                font-family: Arial;
                padding: 1% 2%;
                cursor: pointer;
                text-align: center;
                font-size: 16px;
            }

            <!-- "flexboxes" -->
            .flexbox-container{
                background-color:#3B3C3B;
                width: 100%;
                height: auto;
                display:flex;
                justify-content: center;
            }
            .flexbox-item{
                width: 300px;
                margin:10px;
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

global $DB;
//Read Course ID, Startdate, Enddate (Use "Course Search" input)
$course_name = required_param("course", PARAM_TEXT);
$sql = "SELECT c.* FROM {course} c WHERE upper(c.fullname) like upper(?)";
$records = $DB->get_records_sql($sql, ['%'.$course_name.'%']);

$startDate = null;
$startDateEpoch = null;
$endDateEpoch = null;
$courseId = null;
if (count($records) > 0) {
    foreach ($records as $course) {
        $courseId = $course->id;
        $course_name = $course->fullname;
        $startDateEpoch = $course->startdate;
        $endDateEpoch = $course->enddate;
        $startDate = date('d/m/Y', $course->startdate);
    }
}
else {
    //Error "Course not found"
    echo "<script>alert('There is no Course with the name: \"$course_name\"')</script>";
    return(null);
}

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
//Filter By Name
if (isset($_POST["sfilter"]) AND $_POST["sfilter"] != ""){
    $parts = explode(":",$_POST["sfilter"]);

    if ($parts[0] == "Quiz Grade"){
        $setFilter = 1;
        $filter = trim($parts[1], " ");
        $filterNumber = $parts[2];
        echo $filter;
    }

}



//Needed in Search HTML
$analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/students_overview.php");
$url = $analytics_url . "?course=". $course_name;


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


?>


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
                    <input type="text" placeholder='Filter By "Quiz Grade : <= : 2"' name="sfilter">
                    <button>&#128269; </button>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div style="overflow-y: scroll; width:60%; height:75%; top:25%; right: 20% ;position: absolute ">

            <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                <div style="width:15%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Student Name</span> </div>
                <div style="width:15%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Quiz Grade Ø</span> </div>
                <div style="width:15%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Assignment Grade Ø</span> </div>
                <div style="width:10%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Material Viewed</span> </div>
                <div style="width:10%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Forum Read </span> </div>
                <div style="width:10%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Last Access</span> </div>
            </div>

            <br>
            <hr>
            <br>

            <!-- List Students -->

            <?php
                $analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/student_analytics.php");
                $url = $analytics_url . "?course=". $course_name;
                foreach($students as $student) {
                    if (!(($setFilter == 1) AND !version_compare($student->avgQGrade, $filterNumber,$filter))){

                    } else{
                        continue;
                    }
            ?>


                <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">

                    <div style="width:15%;word-wrap:break-word;">

                        <a href="<?php echo $url ?>&userid=<?php echo $student->id ?>">
                            <?php echo ($student->firstname)." ". ($student->lastname)  ?>
                        </a>
                    </div>

                    <div style="width:15%;word-wrap:break-word;">
                        <?php echo round($student->avgQGrade,2) ?>
                    </div>

                    <div style="width:15%;word-wrap:break-word;">
                        <?php echo round($student->avgAGrade,2) ?>
                    </div>

                    <div style="width:10%;word-wrap:break-word;">
                        <?php echo round($student->materialViewed,2)."%" ?>
                    </div>

                    <div style="width:10%;word-wrap:break-word;">
                        <?php echo round($student->forumRead,2)."%"?>
                    </div>

                    <div style="width:10%;word-wrap:break-word;">
                        <?php echo date('d/m/Y H:i:s', $student->lastAccess) ?>
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

$courseviewurl = new moodle_url('/course/view.php', ['id' => $courseId]);
echo '<a href="' . $courseviewurl . '">Back to the course</a>';