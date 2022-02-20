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
    $student -> postsCreated = getPostsCreated($userID,$courseID);
    $student -> materialViewed = getResourcesViewed($userID,$courseID);
    $student -> forumRead = getForumRead($userID,$courseID);
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

function getPostsCreated($userID,$courseID){
    global $DB;
    $sql = "SELECT count(*) AS amountposts
            FROM {forum_posts} p
            JOIN {forum_discussions} d ON d.id = p.discussion
            WHERE d.course = ? and p.userid = ?";
    $amountPosts = $DB->get_record_sql($sql, [$courseID, $userID]);
    return $amountPosts -> amountposts;
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
        <title>Student Analytics</title>
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

            .gr{
                height: 100%;
                width: 46%;

            }

            .divgraph{
                height: 400px;
                width: 100%;
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

//UserID String + Get Student
$student = null;
$userIDs = array();
if (!isset($_GET["userid"])){
    redirect("$CFG->wwwroot");
}
if (isset($_GET["userid"])){
    $userID = required_param("userid", PARAM_INT);

    foreach ($records as $user){
        $userIDs[] = $user->uid;
    }
    //Check if student is enrolled in course
    if (!in_array($userID,$userIDs)){
        redirect("$CFG->wwwroot");
    }

    $student = getStudent($userID, $courseId);
    foreach ($records as $user){
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



//Course Duration Weeks
if ($endDateEpoch > time()) {
    $courseDurationWeeks = (time() - $startDateEpoch) / 604800;
}else{
    $courseDurationWeeks = ($endDateEpoch - $startDateEpoch)/604800;
}

//Average Quiz Grade + Student Grade

//Read Normalization Value/Max Quiz Grade
$normalizationValueQ = 10;
if (isset($_POST['quizMaxGrade'])) {
    $normalizationValueQ = $_POST['quizMaxGrade'];

    $record = new stdClass();
    $record->courseid = $course_id;
    $record->name = "quizMaxGrade";
    $record->setting = $_POST['quizMaxGrade'];
    $DB->delete_records_select("local_analytics_settings", 'courseid= ? AND name = ?', [$courseId, "quizMaxGrade"]);
    $DB->insert_record("local_analytics_settings", $record);
}else{
    $sql ="SELECT *
           FROM {local_analytics_settings}
           WHERE courseid = ? AND name = ?";
    $day = $DB->get_record_sql($sql,[$courseId, "quizMaxGrade"]);
    if (!is_null($day->setting)){
        $normalizationValueQ = $day->setting;
    }
}

//[[from, to, AverageGrade, AverageTime, Date, studentrgade, studenttime,stddev grade,stddev time]]
$quizGradesO = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $quizGradesO[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0,0,0,0,0);
}

$maxQuizGrade = null;
foreach ($quizGradesO as $key => $week) {
    $sql = "SELECT  avg(g.grade) AS avggrade, std(g.grade) as standardgrade, q.grade AS maxgrade
        FROM mdl_quiz q
        JOIN mdl_quiz_grades g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid in ('$userIDString') AND q.timeopen >= ? AND q.timeopen < ?";

    $quizGrades = $DB->get_record_sql($sql, [$courseId, $week[0], $week[1]]);
    $quizGradesO[$key][4] = date("d.m.y", $week[0]);

    //Read Student Grade
    $sql = "SELECT  g.userid,g.grade
        FROM {quiz} q
        JOIN {quiz_grades} g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid= ? AND q.timeopen >= ? AND q.timeopen < ?";
    $quizGradeStudent = $DB->get_record_sql($sql, [$courseId, $student->id, $week[0], $week[1]]);
    if(!is_null($quizGradeStudent->grade)){
        $quizGradesO[$key][5] = $quizGradeStudent->grade;
    }
    //Graph dont show values if quiz not taken
    else{
        $quizGradesO[$key][2] = null;
        $quizGradesO[$key][3] = null;
        $quizGradesO[$key][5] = null;
        $quizGradesO[$key][6] = null;
        $quizGradesO[$key][7] = null;
        $quizGradesO[$key][8] = null;
        continue;
    }

    if (!is_null($quizGrades->maxgrade)) {
        $maxQuizGrade = $quizGrades->maxgrade;
        $avgGrade = $quizGrades -> avggrade;
        $avgGrade = normalize($avgGrade, 0, $maxQuizGrade);
        $quizGradesO[$key][2] = $avgGrade*$normalizationValueQ;

        $stdGrade = $quizGrades -> standardgrade;
        $stdGrade = normalize($stdGrade, 0, $maxQuizGrade);
        $quizGradesO[$key][7] = $stdGrade*$normalizationValueQ;

        //echo date("m/d/Y H:i:s", $week[0]) . "  " . $week[0] . " " . date("m/d/Y H:i:s", $week[1]) . "  " . $week[1] . " Average Grade: " . ($avgGrade) . "<br>";
    }

    //Average Time Quiz
    $sql = "SELECT avg(a.timefinish - a.timestart) as avgtime, std(a.timefinish - a.timestart) as stdtime
        FROM mdl_quiz q
        JOIN mdl_quiz_attempts a ON q.id = a.quiz
        WHERE q.course = ? AND a.state = 'finished' AND q.timeopen >=  ? AND q.timeopen <  ? AND a.userid IN ('$userIDString')";

    $quizAvgTimes = $DB->get_records_sql($sql,[$courseId, $week[0], $week[1]]);


    //Read Student Time
    $sql = "SELECT a.userid, (a.timefinish - a.timestart) AS times
        FROM mdl_quiz q
        JOIN mdl_quiz_attempts a ON q.id = a.quiz
        WHERE q.course = ? AND a.state = 'finished' AND a.userid = ? AND q.timeopen >=  ? AND q.timeopen <  ?";
    $quizTimeStudent = $DB->get_record_sql($sql,[$courseId, $student->id, $week[0], $week[1]]);
    if (!is_null($quizTimeStudent->times)){
        $quizGradesO[$key][6] = ($quizTimeStudent->times)/60;
    }

    if(count($quizAvgTimes)>0) {
        foreach ($quizAvgTimes as $quizAvgTime) {
            //echo is_null($quizAvgTime->avgTime)."<br>";
            $quizGradesO[$key][3] = ($quizAvgTime->avgtime)/60;
            $quizGradesO[$key][8] = ($quizAvgTime->stdtime)/60;
            //echo "<h1>".$weeks[$key][3]."</h1>";
        }
    }
}



//Read Normalization Value/Max Assignment Grade
$normalizationValueA = 100;
if (isset($_POST['assignmentMaxGrade'])) {
    $normalizationValueA = $_POST['assignmentMaxGrade'];

    $record = new stdClass();
    $record->courseid = $course_id;
    $record->name = "assignmentMaxGrade";
    $record->setting = $_POST['assignmentMaxGrade'];
    $DB->delete_records_select("local_analytics_settings", 'courseid= ? AND name = ?', [$courseId, "assignmentMaxGrade"]);
    $DB->insert_record("local_analytics_settings", $record);
}else{
    $sql ="SELECT *
           FROM {local_analytics_settings}
           WHERE courseid = ? AND name = ?";
    $day = $DB->get_record_sql($sql,[$courseId, "assignmentMaxGrade"]);
    if (!is_null($day->setting)){
        $normalizationValueA = $day->setting;
    }
}


//Average Assignment Grades
//[[from, to, AverageGrade, Average Time until Deadline, date, stdudentgrade, studenttime, stdgrade, stdtime]]
$assignmentGradesO = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $assignmentGradesO[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0,0,0,0,0);
}


$maxAssignmentGrade = null;
foreach ($assignmentGradesO as $key => $week) {
    //Read Avg Grades
    $sql = "SELECT avg(g.grade) AS avggrade, std(g.grade) AS stdgrade
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
        $avgGradeA = $assignmentGradesAvg ->avggrade;
        $avgGradeA = normalize($avgGradeA, 0, $maxAssignmentGrade);
        $assignmentGradesO[$key][2] = $avgGradeA*$normalizationValueA;

        $stdGradeA = $assignmentGradesAvg ->stdgrade;
        $stdGradeA = normalize($stdGradeA, 0, $maxAssignmentGrade);
        $assignmentGradesO[$key][7] = $stdGradeA*$normalizationValueA;

    }
    //Read Student Grade
    $sql = "SELECT g.grade
    FROM {assign} a
    JOIN {assign_grades} g ON a.id = g.assignment
    WHERE a.course = ?  AND g.userid = ?
    AND g.grade IS NOT NULL AND g.grade <> -1
    AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";
    $assignmentGradeStudent = $DB->get_record_sql($sql, [$courseId, $student->id, $week[0], $week[1]]);

    if(!is_null($assignmentGradeStudent->grade)) {
        $avgGradeAS = $assignmentGradeStudent->grade;
        $avgGradeAS = normalize($avgGradeAS, 0, $maxAssignmentGrade);
        $assignmentGradesO[$key][5] = ($avgGradeAS)*$normalizationValueA;
    }
    //Graph dont show values if assignment not taken
    else{
        $assignmentGradesO[$key][2] = null;
        $assignmentGradesO[$key][3] = null;
        $assignmentGradesO[$key][5] = null;
        $assignmentGradesO[$key][6] = null;
        $assignmentGradesO[$key][7] = null;
        $assignmentGradesO[$key][8] = null;
        continue;
    }
    $assignmentGradesO[$key][4] = date("d.m.y", $week[0]);


    //Average Assignment Time until deadline
    //Read Average Time

    $sql = "SELECT avg(a.cutoffdate - s.timecreated) AS avgtime, std(a.cutoffdate - s.timecreated) AS stdtime
        FROM {assign} a
        JOIN {assign_submission} s ON a.id = s.assignment
        WHERE a.course = ?  AND s.userid in ('$userIDString')
        AND s.status = 'submitted'
        AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";

    $avgTimeUntilDeadline = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    if (count($avgTimeUntilDeadline) > 0) {
        foreach ($avgTimeUntilDeadline as $ttl) {
            $assignmentGradesO[$key][3] = ($ttl->avgtime)/3600;
            $assignmentGradesO[$key][8] = ($ttl->stdtime) / 3600;
            //echo "<br><br><br>".($ttl->avgtime)/3600;
        }
    }

    //Read Student Time
    $sql = "SELECT a.id, s.userid, (a.cutoffdate - s.timecreated) AS stime
        FROM {assign} a
        JOIN {assign_submission} s ON a.id = s.assignment
        WHERE a.course = ?  AND s.userid = ?
        AND s.status = 'submitted'
        AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";

    $timeUntilDeadline = $DB->get_record_sql($sql, [$courseId, $student->id, $week[0], $week[1]]);

    if(!is_null($timeUntilDeadline -> stime)) {
        $assignmentGradesO[$key][6] = ($timeUntilDeadline->stime) / 3600;
    }
}




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

//Bookmarked Students
$bookmarkedStudents = array();

$sql = "SELECT userid
        FROM {local_analytics_tagged}
        WHERE courseid = ?";

$bookmarks = $DB->get_records_sql($sql, [$courseId]);
foreach ($bookmarks as $bookmark){
    $bookmarkedStudents[] = $bookmark -> userid;
}
?>
    <!--Average Quiz Grade-->
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        // Load the Visualization API and the corechart package.
        // Load the Visualization API and the corechart package.
        google.charts.load('current', {packages: ['bar']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);
        google.charts.setOnLoadCallback(drawChartI);
        google.charts.setOnLoadCallback(drawChartII);
        google.charts.setOnLoadCallback(drawChartIII);

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChart() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Grade', 'Standard Deviation', 'Student Grade'],
                <?php
                foreach ($quizGradesO as $key => $entry ){
                    if (is_null($quizGradesO[$key][2])){continue;}
                    echo "['".$quizGradesO[$key][4]."',".$quizGradesO[$key][2].",".$quizGradesO[$key][7].",".$quizGradesO[$key][5]."],";
                }
                ?>
            ]);


            // Set chart options
            var options = {
                title:'Quiz Grades',
                subtitle:'Average quiz grade & the student\'s grade',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Grade',
                    viewWindow:{
                        max:<?php echo $normalizationValueQ ?>,
                    }
                }
            };

            options.colors = [
                '#4D9DE0',
                '#E15554',
                '#E1BC29',
            ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_average_quiz_grades'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }


        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChartI() {


            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Time', 'Standard Deviation', 'Student\'s Time'],
                <?php
                foreach ($quizGradesO as $key => $entry ){
                    if (is_null($quizGradesO[$key][2])){continue;}
                    echo "['".$quizGradesO[$key][4]."',".$quizGradesO[$key][3].",".$quizGradesO[$key][8].",".$quizGradesO[$key][6]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Quiz Time',
                subtitle:'Average time needed for a quiz & the student\'s time (in minutes)',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Time (Minutes)',
                }
            };

            options.colors = [
                '#4D9DE0',
                '#E15554',
                '#E1BC29',
            ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_average_quiz_time'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChartII() {


            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Grade', 'Standard Deviation', 'Student Grade'],
                <?php
                foreach ($assignmentGradesO as $key => $entry ){
                    if (is_null($assignmentGradesO[$key][2])){continue;}
                    echo "['".$assignmentGradesO[$key][4]."',".$assignmentGradesO[$key][2].",".$assignmentGradesO[$key][7].",".$assignmentGradesO[$key][5]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Assignment Grades',
                subtitle:'Average assignment grade & the student\'s grade',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Grade',
                    viewWindow:{
                        max:<?php echo $normalizationValueA ?>,
                    }
                }
            };

            options.colors = [
                '#001F3F',
                '#D62828',
                '#F77F00',
            ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_average_assignment_grades'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }


        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChartIII() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Time Before Deadline', 'Standard Deviation', 'Student Time'],
                <?php
                foreach ($assignmentGradesO as $key => $entry ){
                    if (is_null($assignmentGradesO[$key][2])){continue;}
                    echo "['".$assignmentGradesO[$key][4]."',".$assignmentGradesO[$key][3].",".$assignmentGradesO[$key][8].",".$assignmentGradesO[$key][6]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Assignment Time',
                subtitle:'Average time of submission before deadline & the student\'s time (in hours)',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Time (Hours)',
                }
            };

            options.colors = [
                '#001F3F',
                '#D62828',
                '#F77F00',
            ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_average_assignment_time'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }
    </script>


    <!-- General Information -->
    <div style="top:100px; left: 15%; position: absolute; height: 7%; width: 70%;  ">
        <div style="font-family:Arial;  display: flex;  justify-content:space-around; align-items: center">

            <!-- Bookmark Student -->
            <?php $urlBookmarking = new moodle_url($CFG->wwwroot."/local/analytics/"); ?>
            <div>
                <a href="<?php echo $urlBookmarking."student_analytics.php?courseid=".$course_id."&userid=".$student->id."&tag=true" ?>">
                    <div class="tooltip">
                        <img src=<?php if (in_array($student->id, $bookmarkedStudents)){
                            echo "./icons/google/outline_bookmark_black_48dp.png";
                        }
                        else{
                            echo "./icons/google/outline_bookmark_border_black_48dp.png";
                        }?>  width="40px" height="40px">
                        <span class="tooltiptext">Bookmark Student</span>
                    </div>
                </a>
            </div>

            <!-- Student Name -->
            <div style="background-color: white;border-radius: 15px; padding: 10px 10px;">
                <span class="blackColor">Student Name: </span> <span class="blueColor"> <?php echo ($student->firstname)." ".($student->lastname) ?> </span>
            </div>


            <!-- Material Viewed -->
            <div style="background-color: white;border-radius: 15px;padding: 10px 10px;">
                <span class="blackColor">Material Viewed: </span> <span class="blueColor"> <?php echo round($student->materialViewed,2)."%" ?> </span>
            </div>

            <!-- Forum Read -->
            <div style="background-color: white;border-radius: 15px; padding: 10px 10px;">
                <span class="blackColor">Posts Read / Created:</span> <span class="blueColor"> <?php echo round($student->forumRead,2)."% / ".$student->postsCreated?> </span>
            </div>

            <!-- Last Access -->
            <div style="background-color: white;border-radius: 15px;padding: 10px 10px;">
                <span class="blackColor">Last Access: </span>
                <span class="blueColor">
                    <?php if (is_null($student->lastAccess)){
                        echo "-";
                    }else {
                        echo date('d/m/Y H:i:s', $student->lastAccess);
                    }?>
                </span>
            </div>



        </div>
    </div>


    <div style="font-family:Arial;right: 15% ;
    position: absolute;width:70%; height:80%; top: 200px;  display: flex; row-gap: 20px; flex-wrap: wrap; justify-content:space-between;">

        <!-- Notes -->
        <div style="
            height: 300px; width:calc(46% - 10px); text-align: justify;  padding: 10px 5px;
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
            height: 300px;width: calc(46% - 10px); text-align: justify;  padding: 10px 5px;
            background-color:white; border-radius: 15px; overflow-y: scroll;
            font-family:Arial;">
            <?php
            echo'<span class="blackColor" style="text-align: center"> <br>Missed Material:</span><br><hr>';

            foreach ($resourcesNotViewed as $resource) {
                echo ($resource->name)."<br><hr>";
            }

            ?>
        </div>
    </div>

    <div style="font-family:Arial; right: 15% ;
    position: absolute;width:70%; top:540px;  display: flex; flex-wrap: wrap; justify-content:space-between; row-gap: 20px">


    <!-- Average Quiz Grade -->
        <div class="gr">
            <div id="chart_average_quiz_grades" class="divgraph"></div>
        </div>

        <!-- Average Assignment Grade -->
        <div class="gr">
            <div id="chart_average_assignment_grades" class="divgraph"></div>
        </div>

        <!-- Average Quiz Time -->
        <div class="gr">
            <div id="chart_average_quiz_time" class="divgraph"></div>
        </div>


        <!-- Average Assignment Time -->
        <div class="gr">
            <div id="chart_average_assignment_time" class="divgraph"></div>
        </div>


    </div>



    </body>


<?php

