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

$PAGE->set_url(new moodle_url("/local/analytics/grades_time.php"));
?>


    <!-- Layout -->
    <head>
        <title>Performance</title>
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
            .box{
                height: 100px;
                width: 170px;
                padding: 10px 5px;
            }
        </style>
    </head>


<?php
global $DB;
global $CFG;

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


//Course Duration Weeks
if ($endDateEpoch > time()) {
    $courseDurationWeeks = (time() - $startDateEpoch) / 604800;
}else{
    $courseDurationWeeks = ($endDateEpoch - $startDateEpoch)/604800;
}

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

//Average Quiz Grade
//[[from, to, AverageGrade, AverageTime, Date, StdGrade, StdTime]]
$quizGradesO = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $quizGradesO[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0,0,0);
}

$maxQuizGrade = null;
foreach ($quizGradesO as $key => $week) {
    $sql = "SELECT  avg(g.grade) AS avggrade, std(g.grade) as standardgrade, q.grade AS maxgrade
        FROM mdl_quiz q
        JOIN mdl_quiz_grades g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid in ('$userIDString') AND q.timeopen >= ? AND q.timeopen < ?";

    $quizGrades = $DB->get_record_sql($sql, [$courseId, $week[0], $week[1]]);
    $quizGradesO[$key][4] = date("d.m.Y", $week[0]);
    $sum = 0;
    if (!is_null($quizGrades->maxgrade)) {
        $maxQuizGrade = $quizGrades->maxgrade;
        $avgGrade = $quizGrades -> avggrade;
        $avgGrade = normalize($avgGrade, 0, $maxQuizGrade);
        $quizGradesO[$key][2] = $avgGrade*$normalizationValueQ;

        $stdGrade =  $quizGrades -> standardgrade;
        $stdGrade = normalize($stdGrade, 0, $maxQuizGrade);
        $quizGradesO[$key][5] = $stdGrade*$normalizationValueQ;

        //echo date("m/d/Y H:i:s", $week[0]) . "  " . $week[0] . " " . date("m/d/Y H:i:s", $week[1]) . "  " . $week[1] . " Average Grade: " . ($avgGrade) . "<br>";
    }
}

//Average Time Quiz
foreach ($quizGradesO as $key => $week) {
        $sql = "SELECT avg(a.timefinish - a.timestart) as avgtime, std(a.timefinish - a.timestart) as stdtime
        FROM mdl_quiz q
        JOIN mdl_quiz_attempts a ON q.id = a.quiz
        WHERE q.course = ? AND a.state = 'finished ' AND q.timeopen >=  ? AND q.timeopen <  ? AND a.userid IN ('$userIDString')";

        $quizAvgTimes = $DB->get_records_sql($sql,[$courseId, $week[0], $week[1]]);
        if(count($quizAvgTimes)>0) {
            foreach ($quizAvgTimes as $quizAvgTime) {
                //echo is_null($quizAvgTime->avgTime)."<br>";
                $quizGradesO[$key][3] = ($quizAvgTime->avgtime)/60;
                $quizGradesO[$key][6] = ($quizAvgTime->stdtime)/60;
            }
        }
}



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
//[[from, to, AverageGrade, Average Time until Deadline, date, stdgrade, stdtime]]
$assignmentGradesO = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $assignmentGradesO[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0,0,0);
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
        $assignmentGradesO[$key][5] = $stdGradeA*$normalizationValueA;
    }

    $assignmentGradesO[$key][4] = date("d.m.Y", $week[0]);

}


//Average Assignment Time until deadline
//Read Average Time
foreach ($assignmentGradesO as $key => $week) {
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
            $assignmentGradesO[$key][6] = ($ttl->stdtime)/3600;
            //echo "<br><br><br>".($ttl->avgtime)/3600;
        }
    }
}

?>
    <!--Average Quiz Grade-->
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        // Load the Visualization API and the corechart package.
        google.charts.load('current', {packages: ['corechart', 'bar']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChart() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Grade', 'Standard Deviation'],
                <?php
                foreach ($quizGradesO as $key => $entry ){
                    echo "['".$quizGradesO[$key][4]."',".$quizGradesO[$key][2].",".$quizGradesO[$key][5]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Average Quiz Grades',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Average Grade',
                    viewWindow:{
                        max:<?php echo $normalizationValueQ ?>,
                    }
                }
            };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.ColumnChart(document.getElementById('chart_average_quiz_grades'));
            chart.draw(data, options);
        }
    </script>


    <!--Average Quiz Time-->
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        // Load the Visualization API and the corechart package.
        google.charts.load('current', {packages: ['corechart', 'bar']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChart() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Time Needed', 'Standard Deviation'],
                <?php
                foreach ($quizGradesO as $key => $entry ){
                    echo "['".$quizGradesO[$key][4]."',".$quizGradesO[$key][3].",".$quizGradesO[$key][6]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Average Quiz Time',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Average Time (Minutes)',
                }
            };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.ColumnChart(document.getElementById('chart_average_quiz_time'));
            chart.draw(data, options);
        }
    </script>

    <!--Average Assignment Grade-->
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        // Load the Visualization API and the corechart package.
        google.charts.load('current', {packages: ['corechart', 'bar']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChart() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Grade', 'Standard Deviation'],
                <?php
                foreach ($assignmentGradesO as $key => $entry ){
                    echo "['".$assignmentGradesO[$key][4]."',".$assignmentGradesO[$key][2].",".$assignmentGradesO[$key][5]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Average Assignment Grades',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Average Grade',
                    viewWindow:{
                        max:<?php echo $normalizationValueA ?>,
                    }
                }
            };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.ColumnChart(document.getElementById('chart_average_assignment_grades'));
            chart.draw(data, options);
        }
    </script>

    <!--Average Assignment Time-->
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        // Load the Visualization API and the corechart package.
        google.charts.load('current', {packages: ['corechart', 'bar']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChart() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Average Hours before Deadline', 'Standard Deviation'],
                <?php
                foreach ($assignmentGradesO as $key => $entry ){
                    echo "['".$assignmentGradesO[$key][4]."',".$assignmentGradesO[$key][3].",".$assignmentGradesO[$key][6]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Average Submission before Deadline',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Average Time (Hours)',
                }
            };

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.visualization.ColumnChart(document.getElementById('chart_average_assignment_time'));
            chart.draw(data, options);
        }
    </script>


    <!-- Heading View -->
    <div style="position: fixed;top: 70px; left:125px; font-family: Arial; z-index: 4">
        <h2>Performance</h2>
    </div>

    <!-- General Information -->

    <div style="top: 140px; right: 15%; position: absolute; height: 7%; width: 70%;  ">
        <div style="font-family:Arial;  display: flex;  justify-content:space-between; align-items: center">

            <!-- Current Quiz Participation -->
            <div style="background-color:white;
                text-align: center;
                border-radius: 15px"
                class="box">
                <p>
                    <span class="blackColor"> Current Quiz Participation:</span> <br><br><span class ="blueColor"><?php echo count($amountUsersTakenQuiz)."/".count($userIDs)." Students" ?></span>
                </p>
            </div>

            <!-- Average Quiz Participation -->
            <div style="background-color:white;
                text-align: center;
                border-radius: 15px "
                 class="box">
                <p>
                    <span class="blackColor"> Average Quiz Participation:</span> <br><br><span class ="blueColor"><?php echo round($averageQuizParticipation,2)."%" ?></span>
                </p>
            </div>

            <!-- Current Assignment Participation -->
            <div style="background-color:white;
                text-align: center;
                border-radius: 15px "
                 class="box">
                <p>
                    <span class="blackColor"> Current Assignment Participation:</span> <br><br><span class ="blueColor"><?php echo count($amountUsersTakenAssignment)."/".count($userIDs)." Students" ?></span>
                </p>
            </div>

            <!-- Average Assignment Participation -->
            <div style="background-color:white;
                text-align: center;
                border-radius: 15px "
                 class="box">
                <p>
                    <span class="blackColor"> Average Assignment Participation:</span> <br><br><span class ="blueColor"><?php echo round($averageAssignmentParticipation,2)."%" ?></span>
                </p>
            </div>

        </div>
    </div>


    <div style="font-family:Arial; right: 15% ;
    position: absolute;width:70%; height:50%; top:280px;  display: flex; flex-wrap: wrap; justify-content:space-between;">


        <!-- Average Quiz Grade -->
        <div style="height: 400px;width: 500px;">
            <div id="chart_average_quiz_grades" style="height: 350px;width: 500px;"></div>
            <!-- Set Max Quiz Grade -->
            <div>
                <form action="<?php echo $CFG->wwwroot.'/local/analytics/grades_time.php?courseid='.$course_id ?>" method="post">
                    <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                        <div>
                            <input class="inputfield" type="number" min="1" name="quizMaxGrade" placeholder="Set Max Quiz Grade"
                                   style="font-family: Arial;width:180px; ">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Average Assignment Grade -->
        <div style="height: 400px;width: 500px;">
            <div id="chart_average_assignment_grades" style="height: 350px;width: 500px;"></div>
            <!-- Set Max Assignment Grade -->
            <div>
                <form action="<?php echo $CFG->wwwroot.'/local/analytics/grades_time.php?courseid='.$course_id ?>" method="post">
                    <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                        <div>
                            <input class="inputfield" type="number" min="1" name="assignmentMaxGrade" placeholder="Set Max Assignment Grade"
                                   style="font-family: Arial;width:180px; ">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Average Quiz Time -->
        <div style="height: 350px;width: 500px;">
            <div id="chart_average_quiz_time" style="height: 350px;width: 500px;"></div>
        </div>

        <!-- Average Assignment Time -->
        <div style="height: 350px;width: 500px;">
            <div id="chart_average_assignment_time" style="height: 350px;width: 500px;"></div>
        </div>
    </div>


    </body>
<?php