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

function getResourcesNotViewed($userID,$courseID){
    global $DB;
    $sql = "SELECT * 
            FROM {resource} 
            WHERE course = ?";
    $resources = $DB->get_records_sql($sql, [$courseID]);
    $amountResources = count($resources);
    if ($amountResources == 0) {
        return null;
    }
    $resourcesNotViewed = array();

    foreach ($resources as $resource){
        $sql = "SELECT count(*) as amountviewed
        FROM {logstore_standard_log} 
        WHERE courseid = ? AND userid = ?
        AND action='viewed' AND component = 'mod_resource' AND target = 'course_module' AND objectid = ?";
        $resourceViewed = $DB->get_record_sql($sql, [$courseID,$userID,$resource->id]);
        if (($resourceViewed->amountviewed) == 0){
            $resourcesNotViewed[] = $resource;
        }
    }
    return ($resourcesNotViewed);
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

//UserID String + Get Student
$student = null;
$userIDs = array();
if (isset($_GET["userid"])){
    $userID = required_param("userid", PARAM_INT);
    $student = getStudent($userID, $courseId);

    foreach ($records as $user){
        $userIDs[] = $user->uid;

        if (($user->uid) == $userID){
            $student -> firstname = $user -> firstname;
            $student -> lastname = $user -> lastname;
        }
    }
}
$userIDString = join("','",$userIDs);

//Resources not viewed
$resourcesNotViewed = getResourcesNotViewed($student->id,$courseId);

//Get Notes
$sql = "SELECT * FROM {local_analytics_notes} c 
        WHERE name = ? AND courseid = ?";
$notes = $DB->get_records_sql($sql, [($student -> firstname)." ".($student -> lastname), $courseId]);


//Average Quiz Grade + Student Grade
$courseDurationWeeks = ($endDateEpoch - $startDateEpoch)/604800;
//[[from, to, AverageGrade, AverageTime]]
$weeks = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $weeks[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0);
}

$xAxisDatesQuiz = array();
$yAxisGradesQuiz = array();
$yAxisGradesQuizStudent = array();
$maxQuizGrade = null;
foreach ($weeks as $key => $week) {
    $sql = "SELECT  g.userid,g.grade, q.grade AS maxgrade
        FROM {quiz} q
        JOIN {quiz_grades} g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid in ('$userIDString') AND q.timeopen >= ? AND q.timeopen < ?";

    $quizGrades = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $xAxisDatesQuiz[] = date("m.d.Y", $week[0]);
    $sum = 0;


    //Read Student Grade
    $sql = "SELECT  g.userid,g.grade
        FROM {quiz} q
        JOIN {quiz_grades} g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid= ? AND q.timeopen >= ? AND q.timeopen < ?";
    $quizGradeStudent = $DB->get_record_sql($sql, [$courseId, $student->id, $week[0], $week[1]]);
    $yAxisGradesQuizStudent[] = $quizGradeStudent->grade;
    $weeks[$key][3] = $quizGradeStudent->grade;




    if (count($quizGrades) > 0) {
        foreach ($quizGrades as $grade) {
            $sum += $grade->grade;
            $maxQuizGrade = $grade -> maxgrade;
        }
        $avgGrade = ($sum / count($quizGrades));
        $weeks[$key][2] = $avgGrade;
        $yAxisGradesQuiz[] = $avgGrade;
        echo date("m/d/Y H:i:s", $week[0]) . "  " . $week[0] . " " . date("m/d/Y H:i:s", $week[1]) . "  " . $week[1] . " Average Grade: " . ($avgGrade) . "<br>";
    } else {
        $yAxisGradesQuiz[] = null;
    }
}

//Graph Average Quiz Grade
$graphQ = new graph();
$graphQ->plot( range(1,ceil($courseDurationWeeks)), $yAxisGradesQuiz, 'o');
$graphQ->plot( range(1,ceil($courseDurationWeeks)),$yAxisGradesQuizStudent,'x');
$graphQ->xlabel( 'Week' );
$graphQ->axes([1,ceil($courseDurationWeeks),0,$maxQuizGrade]);
$graphQ->legend( $legend = false );
$graphQ->title("Average Quiz Grade (o) & Student Grade (x)");

//Average Time Quiz
$yAxisAverageTimeQuiz = array();
$yAxisTimeQuizStudent = array();
foreach ($weeks as $key => $week) {
    $sql = "SELECT avg(a.timefinish - a.timestart) as avgtime
        FROM mdl_quiz q
        JOIN mdl_quiz_attempts a ON q.id = a.quiz
        WHERE q.course = ? AND q.timeopen >=  ? AND q.timeopen <  ? AND a.userid IN ('$userIDString')";

    $quizAvgTimes = $DB->get_records_sql($sql,[$courseId, $week[0], $week[1]]);

    //Read Student Time
    $sql = "SELECT a.userid, (a.timefinish - a.timestart) as time
        FROM mdl_quiz q
        JOIN mdl_quiz_attempts a ON q.id = a.quiz
        WHERE q.course = ?  AND a.userid = ? AND q.timeopen >=  ? AND q.timeopen <  ?";
    $quizTimeStudent = $DB->get_record_sql($sql,[$courseId, $student->id, $week[0], $week[1]]);
    $yAxisTimeQuizStudent[] = ($quizTimeStudent->time)/60;

    if(count($quizAvgTimes)>0) {
        foreach ($quizAvgTimes as $quizAvgTime) {
            $yAxisAverageTimeQuiz[] = ($quizAvgTime->avgtime)/60;
            //echo is_null($quizAvgTime->avgTime)."<br>";
            $weeks[$key][3] = ($quizAvgTime->avgtime)/60;
        }
    }
    else{
        $yAxisAverageTimeQuiz[] = 0;
    }
}

//Graph Average Time Quiz
$graphQT = new graph();
$graphQT->plot( range(1,ceil($courseDurationWeeks)), $yAxisAverageTimeQuiz, 'o');
$graphQT->plot( range(1,ceil($courseDurationWeeks)), $yAxisTimeQuizStudent, 'x');
$graphQT->xlabel( 'Week' );
$graphQT->axes([1,ceil($courseDurationWeeks),0,max(array_merge($yAxisAverageTimeQuiz,$yAxisTimeQuizStudent))]);
$graphQT->legend( $legend = false );
$graphQT->title("Average Time Needed (o) & Student Time (x) - Minutes");


//Average Assignment Grade + Student Grade
//[[from, to, AverageGrade, Average Time until Deadline]]
$weeksA = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $weeksA[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0);
}

$xAxisDatesAssignment = array();
$yAxisGradesAssignment = array();
$yAxisGradeStudent = array();
$maxAssignmentGrade = null;
foreach ($weeksA as $key => $week) {
    //Read Avg Grades
    $sql = "SELECT avg(g.grade) AS avggrade
    FROM {assign} a
    JOIN {assign_grades} g ON a.id = g.assignment
    WHERE a.course = ?  AND g.userid in ('$userIDString')
    AND g.grade IS NOT NULL AND g.grade <> -1
    AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";
    $assignmentGradesAvg = $DB->get_record_sql($sql, [$courseId, $week[0], $week[1]]);

    //Read Student Grade
    $sql = "SELECT g.grade
    FROM {assign} a
    JOIN {assign_grades} g ON a.id = g.assignment
    WHERE a.course = ?  AND g.userid = ?
    AND g.grade IS NOT NULL AND g.grade <> -1
    AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";
    $assignmentGradeStudent = $DB->get_record_sql($sql, [$courseId, $student->id, $week[0], $week[1]]);
    $yAxisGradeStudent[] = $assignmentGradeStudent -> grade;

    //Read Max Grade
    $sql1 = "SELECT a.id, a.grade
    FROM {assign} a
    WHERE a.course = ? 
    AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";
    $assignmentMaxGrade = $DB->get_records_sql($sql1, [$courseId, $week[0], $week[1]]);
    foreach ($assignmentMaxGrade as $maxGrade){
        $maxAssignmentGrade = $maxGrade -> grade;
    }

    $yAxisGradesAssignment[] = $assignmentGradesAvg ->avggrade;
    $weeksA[$key][2] = $assignmentGradesAvg ->avggrade;



    $xAxisDatesAssignment[] = date("m.d.Y", $week[0]);

}
//Graph Average Assignment Grade
$graphA = new graph();
$graphA->plot( range(1,ceil($courseDurationWeeks)), $yAxisGradesAssignment, 'o');
$graphA->plot( range(1,ceil($courseDurationWeeks)), $yAxisGradeStudent, 'x');
$graphA->xlabel( 'Week' );
$graphA->axes([1,ceil($courseDurationWeeks),0,$maxAssignmentGrade]);
$graphA->legend( $legend = false );
$graphA->title("Average Assignment Grade (o) & Student Grade (x)");


//Average Assignment Time until deadline
$yAxisAverageTimeAssignment = array();
$yAxisTimeAssignmentStudent = array();

//Read Average Time
foreach ($weeksA as $key => $week) {
    $sql = "SELECT avg(a.cutoffdate - s.timecreated) AS avgtime
        FROM {assign} a
        JOIN {assign_submission} s ON a.id = s.assignment
        WHERE a.course = ?  AND s.userid in ('$userIDString')
        AND s.status = 'submitted'
        AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";

    $avgTimeUntilDeadline = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    if (count($avgTimeUntilDeadline) > 0) {
        foreach ($avgTimeUntilDeadline as $ttl) {
            $weeksA[$key][3] = ($ttl->avgtime)/3600;
            $yAxisAverageTimeAssignment[] = ($ttl->avgtime)/3600;
        }
    }
    else{
        $yAxisAverageTimeAssignment[] = 0;
    }

    //Read Student Time
    $sql = "SELECT a.id, s.userid, (a.cutoffdate - s.timecreated) AS stime
        FROM {assign} a
        JOIN {assign_submission} s ON a.id = s.assignment
        WHERE a.course = ?  AND s.userid = ?
        AND s.status = 'submitted'
        AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";

    $timeUntilDeadline = $DB->get_record_sql($sql, [$courseId, $student->id, $week[0], $week[1]]);
    $yAxisTimeAssignmentStudent[] = ($timeUntilDeadline -> stime)/3600;
}

//Graph Average Assignment Time
$graphAT = new graph();
$graphAT->plot( range(1,ceil($courseDurationWeeks)), $yAxisAverageTimeAssignment, 'o');
$graphAT->plot( range(1,ceil($courseDurationWeeks)), $yAxisTimeAssignmentStudent, 'x');
$graphAT->xlabel( 'Week' );
$graphAT->axes([1,ceil($courseDurationWeeks),0,max(array_merge($yAxisAverageTimeAssignment, $yAxisTimeAssignmentStudent))]);
$graphAT->legend( $legend = false );
$graphAT->title("Average Time until Deadline (o) & Student Time (x) - Hours");


//Tag Student
if (isset($_GET['tag']) AND ($_GET['tag'] == "true")) {
    //Check if Student is part of DB
    $sql = "SELECT count(*) AS amount
            FROM {local_analytics_tagged}
            WHERE userid = ? AND courseid = ?";
    $tagged = $DB->get_record_sql($sql, [$student->id, $courseId]);

    if (($tagged->amount) > 0){
        $DB->delete_records_select("local_analytics_tagged", 'userid= ?',[$student->id]);
    }else {
        //Insert Student into Tagged Database
        $record = new stdClass();
        $record->courseid = $courseId;
        $record->userid = $student->id;
        $record->name = ($student->firstname) . " " . ($student->lastname);
        $DB->insert_record("local_analytics_tagged", $record);
    }
}

?>
    <!-- General Information -->

    <div style="top: 9%; right: 3%; position: absolute; height: 7%; width: 90%;  ">
        <div style="font-family:Arial;  display: flex;  justify-content:space-around; align-items: center">

            <!-- Bookmark Student -->
            <?php $urlBookmarking = new moodle_url($CFG->wwwroot."/local/analytics/"); ?>
            <div>
                <a href="<?php echo $urlBookmarking."student_analytics.php?course=".$course_name."&userid=".$student->id."&tag=true" ?>">
                    <img src="./icons/Pin.png" width="40px" height="40px">
                </a>
            </div>

            <!-- Student Name -->
            <div style="background-color: white;border-radius: 15px; padding: 1% 3%;">
                <span class="blackColor">Student Name: </span> <span class="blueColor"> <?php echo ($student->firstname)." ".($student->lastname) ?> </span>
            </div>

            <!-- Material Viewed -->
            <div style="background-color: white;border-radius: 15px; padding: 1% 3%;">
                <span class="blackColor">Material Viewed: </span> <span class="blueColor"> <?php echo round($student->materialViewed,2)."%" ?> </span>
            </div>

            <!-- Forum Read -->
            <div style="background-color: white;border-radius: 15px; padding: 1% 3%;">
                <span class="blackColor">Forum Read:</span> <span class="blueColor"> <?php echo round($student->forumRead,2)."%"?> </span>
            </div>

            <!-- Last Access -->
            <div style="background-color: white;border-radius: 15px; padding: 1% 3%;">
                <span class="blackColor">Last Access: </span> <span class="blueColor"> <?php echo date('d/m/Y H:i:s', $student->lastAccess) ?> </span>
            </div>



        </div>
    </div>


    <div style="font-family:Arial;overflow-y: scroll;top:25%; right: 3% ;
    position: absolute;width:90%; height:80%; top:20%;  display: flex; gap: 10px 40px; flex-wrap: wrap; justify-content:space-evenly;">

        <!-- Notes -->
        <div style="
            width:38%; height: 40%; text-align: justify;  padding: 1% 1%;
            background-color:white; border-radius: 15px; overflow-y: scroll;
            font-family:Arial;word-wrap:break-word;">
            <?php
            echo'<span class="blackColor" style="text-align: center"> <br>Last Notes:</span><hr>';

            foreach (array_reverse($notes) as $note) {
                echo "<u>".($note->context)."</u>";
                echo "<br>";
                echo date('d/m/Y H:i:s',($note->date));
                echo "<br>";
                echo "<br>";
                echo "".$note->notetext ."<br><hr><br>". "";
            }

            ?>
        </div>

        <!-- Material not viewed -->
        <div style="

            width:38%; height: 40%; text-align: justify;  padding: 1% 1%;
            background-color:white; border-radius: 15px; overflow-y: scroll;
            font-family:Arial;">
            <?php
            echo'<span class="blackColor" style="text-align: center"> <br>Missed Material:</span><br><hr>';

            foreach ($resourcesNotViewed as $resource) {
                echo ($resource->name)."<br><hr>";
            }

            ?>
        </div>

        <!-- Average Quiz Grade -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphQ->output_gd_png_base64( )?>">
        </div>

        <!-- Average Quiz Time -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphQT->output_gd_png_base64( )?>">
        </div>

        <!-- Average Assignment Grade -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphA->output_gd_png_base64( )?>">
        </div>

        <!-- Average Assignment Time -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphAT->output_gd_png_base64( )?>">
        </div>
    </div>


    </body>


<?php

