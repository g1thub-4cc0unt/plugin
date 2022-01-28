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

$PAGE->set_url(new moodle_url("/local/analytics/grades_time.php"));
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
        </style>
    </head>


<?php

//Read Course ID, Startdate, Enddate (Use "Course Search" input)
global $DB;
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


//Average Quiz Participation
$sql = "SELECT q.id
        FROM {quiz} q
        WHERE q.course = ? AND q.timeopen < ?"; //the Quiz must have been open before so that it is included in the average grade calculation
$amountQuizzes = $DB->get_records_sql($sql, [$courseId, time()]);

//g.* - each entry == Quiz done by User
$sql = "SELECT g.*
        FROM {quiz} q
        JOIN {quiz_grades} g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid in ('$userIDString')";
$amountUsersTakenQuizzes = $DB->get_records_sql($sql, [$courseId]);

$averageQuizParticipation = null;
if (count($amountUsersTakenQuizzes) > 0) {
    $averageQuizParticipation= (count($amountUsersTakenQuizzes)/(count($amountQuizzes)*count($userIDs)))*100;
}


//Current Quiz Participation
$sql = "SELECT g.userid
        FROM {quiz} q
        JOIN {quiz_grades} g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid in ('$userIDString') AND q.timeopen < ? AND q.timeclose > ?";
$amountUsersTakenQuiz = $DB->get_records_sql($sql, [$courseId, time(), time()]);


//Average Quiz Grade
$courseDurationWeeks = ($endDateEpoch - $startDateEpoch)/604800;
//[[from, to, AverageGrade, AverageTime]]
$weeks = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $weeks[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0);
}

$xAxisDatesQuiz = array();
$yAxisGradesQuiz = array();
$maxQuizGrade = null;
foreach ($weeks as $key => $week) {
    $sql = "SELECT  g.userid,g.grade, q.grade AS maxgrade
        FROM {quiz} q
        JOIN {quiz_grades} g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid in ('$userIDString') AND q.timeopen >= ? AND q.timeopen < ?";

    $quizGrades = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $xAxisDatesQuiz[] = date("m.d.Y", $week[0]);
    $sum = 0;
    if (count($quizGrades) > 0) {
        foreach ($quizGrades as $grade) {
            $sum += $grade->grade;
            $maxQuizGrade = $grade -> maxgrade;
        }
        $avgGrade = ($sum / count($quizGrades));
        $weeks[$key][2] = $avgGrade;
        $yAxisGradesQuiz[] = $avgGrade;
        //echo date("m/d/Y H:i:s", $week[0]) . "  " . $week[0] . " " . date("m/d/Y H:i:s", $week[1]) . "  " . $week[1] . " Average Grade: " . ($avgGrade) . "<br>";
    } else {
        $yAxisGradesQuiz[] = null;
    }
}

//Graph Average Quiz Grade
    $graphQ = new graph();
    $graphQ->plot( range(1,ceil($courseDurationWeeks)), $yAxisGradesQuiz, 'o');
    $graphQ->xlabel( 'Week' );
    $graphQ->axes([1,ceil($courseDurationWeeks),0,$maxQuizGrade]);
    $graphQ->legend( $legend = false );
    $graphQ->title("Average Quiz Grade");

//Average Time Quiz
$yAxisAverageTimeQuiz = array();
foreach ($weeks as $key => $week) {
        $sql = "SELECT avg(a.timefinish - a.timestart) as avgtime
        FROM mdl_quiz q
        JOIN mdl_quiz_attempts a ON q.id = a.quiz
        WHERE q.course = ? AND q.timeopen >=  ? AND q.timeopen <  ? AND a.userid IN ('$userIDString')";

        $quizAvgTimes = $DB->get_records_sql($sql,[$courseId, $week[0], $week[1]]);
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
$graphQT->xlabel( 'Week' );
$graphQT->axes([1,ceil($courseDurationWeeks),0,max($yAxisAverageTimeQuiz)]);
$graphQT->legend( $legend = false );
$graphQT->title("Average Time Needed - Minutes");


//Current Assignment Participation
$sql = "SELECT DISTINCT s.userid
    FROM {assign} a
    JOIN {assign_submission} s ON a.id = s.assignment
    WHERE a.course = ? AND s.status = 'submitted' AND s.userid in ('$userIDString') AND a.allowsubmissionsfromdate < ? AND a.cutoffdate > ?";
$amountUsersTakenAssignment = $DB->get_records_sql($sql, [$courseId, time(), time()]);


//Average Assignment Participation
//Count Submissions
$sql = "SELECT count(*) AS amount
    FROM {assign} a
    JOIN {assign_submission} s ON a.id = s.assignment
    WHERE a.course = ? AND s.status = 'submitted' AND s.userid in ('$userIDString') AND a.allowsubmissionsfromdate < ?";
$amountUsersTakenAssignments = $DB->get_records_sql($sql, [$courseId, time()]);

$amountUTAs = null;
foreach($amountUsersTakenAssignments as $key){
    $amountUTAs = $key->amount;
}

$sql = "SELECT a.id
    FROM {assign} a
    WHERE a.course = ? AND a.allowsubmissionsfromdate < ?";
$amountAssignments = $DB->get_records_sql($sql, [$courseId, time()]);

if (count($amountAssignments) > 0 ){
    $averageAssignmentParticipation = (($amountUTAs/(count($userIDs)*count($amountAssignments)))*100);
}


//Average Assignment Grades
//[[from, to, AverageGrade, Average Time until Deadline]]
$weeksA = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $weeksA[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0);
}

$xAxisDatesAssignment = array();
$yAxisGradesAssignment = array();
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
$graphA->xlabel( 'Week' );
$graphA->axes([1,ceil($courseDurationWeeks),0,$maxAssignmentGrade]);
$graphA->legend( $legend = false );
$graphA->title("Average Assignment Grade");

//Average Assignment Time until deadline
$yAxisAverageTimeAssignment = array();
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
            echo "<br><br><br>".($ttl->avgtime)/3600;
        }
    }
    else{
        $yAxisAverageTimeAssignment[] = 0;
    }
}

//Graph Average Assignment Grade
$graphAT = new graph();
$graphAT->plot( range(1,ceil($courseDurationWeeks)), $yAxisAverageTimeAssignment, 'o');
$graphAT->xlabel( 'Week' );
$graphAT->axes([1,ceil($courseDurationWeeks),0,max($yAxisAverageTimeAssignment)]);
$graphAT->legend( $legend = false );
$graphAT->title("Average Time until Deadline - Hours");
?>
    <!-- General Information -->

    <div style="top: 9%; right: 3%; position: absolute; height: 7%; width: 90%;  ">
        <div style="font-family:Arial;  display: flex;  justify-content:space-around; align-items: center">

            <!-- Current Quiz Participation -->
            <div style="background-color:white;
                text-align: center;
                padding: 1% 3%;
                border-radius: 15px ">
                <p>
                    <span class="blackColor"> Current Quiz Participation:</span> <br><br><span class ="blueColor"><?php echo count($amountUsersTakenQuiz)."/".count($userIDs) ?></span>
                </p>
            </div>

            <!-- Average Quiz Participation -->
            <div style="background-color:white;
                text-align: center;
                padding: 1% 3%;
                border-radius: 15px ">
                <p>
                    <span class="blackColor"> Average Quiz Participation:</span> <br><br><span class ="blueColor"><?php echo $averageQuizParticipation."%" ?></span>
                </p>
            </div>

            <!-- Current Assignment Participation -->
            <div style="background-color:white;
                text-align: center;
                padding: 1% 3%;
                border-radius: 15px ">
                <p>
                    <span class="blackColor"> Current Assignment Participation:</span> <br><br><span class ="blueColor"><?php echo count($amountUsersTakenAssignment)."/".count($userIDs) ?></span>
                </p>
            </div>

            <!-- Average Assignment Participation -->
            <div style="background-color:white;
                text-align: center;
                padding: 1% 3%;
                border-radius: 15px ">
                <p>
                    <span class="blackColor"> Average Assignment Participation:</span> <br><br><span class ="blueColor"><?php echo round($averageAssignmentParticipation,2)."%" ?></span>
                </p>
            </div>

        </div>
    </div>


    <div style="font-family:Arial;overflow-y: scroll;top:25%; right: 3% ;
    position: absolute;width:90%; height:80%; top:20%;  display: flex; gap: 10px 40px; flex-wrap: wrap; justify-content:space-evenly;">


        <!-- Average Quiz Grade -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphQ->output_gd_png_base64( )?>">
        </div>

        <!-- Average Assignment Grade -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphA->output_gd_png_base64( )?>">
        </div>

        <!-- Average Quiz Time -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphQT->output_gd_png_base64( )?>">
        </div>

        <!-- Average Assignment Time -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphAT->output_gd_png_base64( )?>">
        </div>
    </div>


    </body>
<?php
//Right Arrow
$analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/material_usage.php");
echo'<div style=" width:2.5%;
                height:5%; position:absolute; top:95%; right:2%;">
   
    <a href="' . $analytics_url . "?course=". $course_name. '">
        <img style="height: 100%; width: 100%; object-fit: contain" src="./icons/RightArrow.png" class = "center">
    </a>
</div>';
//Left Arrow
$analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/grades_time.php");
echo'<div style=" width:2.5%;
                height:5%; position:absolute; top:95%; right:4%;">
   
    <a href="' . $analytics_url . "?course=". $course_name. '">
        <img style="height: 100%; width: 100%; object-fit: contain" src="./icons/LeftArrow.png" class = "center">
    </a>
</div>';
