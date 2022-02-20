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

            .box{
                height: 100px;
                width: 160px;
                padding: 10px 10px;
            }

            .gr{
                height: 100%;
                width: 46%;

            }

            .divgraph{
                height: 400px;
                width: 100%;
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


//Average Quiz Participation
$sql = "SELECT q.id
        FROM {quiz} q
        WHERE q.course = ? AND q.timeopen < ?"; //the Quiz must have been open before so that it is included in the average grade calculation
$amountQuizzes = $DB->get_records_sql($sql, [$courseId, time()]);
echo count($amountQuizzes);

//avoid multiple attempts - each entry == Quiz attempted by User       AND a.state = 'finished'
$sql = "SELECT *
        FROM {quiz} q
        JOIN {quiz_attempts} a ON q.id = a.quiz
        WHERE q.course = ?  AND a.userid in ('$userIDString')
        GROUP BY a.userid,a.quiz";
$amountUsersTakenQuizzes = $DB->get_records_sql($sql, [$courseId]);
echo count($amountUsersTakenQuizzes);

$averageQuizParticipation = null;
if (count($amountUsersTakenQuizzes) > 0) {
    $averageQuizParticipation= (count($amountUsersTakenQuizzes)/(count($amountQuizzes)*count($userIDs)))*100;
}


//Current Quiz Participation
$sql = "SELECT DISTINCT userid
        FROM {quiz} q
        JOIN {quiz_attempts} a ON q.id = a.quiz
        WHERE q.course = ?  AND a.userid in ('$userIDString') AND q.timeopen < ? AND q.timeclose > ?";
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
//[[from, to, AverageGrade, AverageTime, Date, StdGrade, StdTime, Amount Students taken Quiz]]
$quizGradesO = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $quizGradesO[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0,0,0,0);
}


$maxQuizGrade = null;
foreach ($quizGradesO as $key => $week) {

    //Number of Students Taken Quiz
    $sql = "SELECT DISTINCT userid
        FROM {quiz} q
        JOIN {quiz_attempts} a ON q.id = a.quiz
        WHERE q.course = ? AND a.state = 'finished' AND a.userid in ('$userIDString')  AND q.timeopen >= ? AND q.timeopen < ?";
    $amountUsersTQ = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $quizGradesO[$key][7] = count($amountUsersTQ);

    //Average Quiz Grade
    $sql = "SELECT  avg(g.grade) AS avggrade, std(g.grade) as standardgrade, q.grade AS maxgrade
        FROM mdl_quiz q
        JOIN mdl_quiz_grades g ON q.id = g.quiz
        WHERE q.course = ? AND g.userid in ('$userIDString') AND q.timeopen >= ? AND q.timeopen < ?";

    $quizGrades = $DB->get_record_sql($sql, [$courseId, $week[0], $week[1]]);
    $quizGradesO[$key][4] = date("d.m.y", $week[0]);
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

         //Average Time Quiz
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



//Current Assignment Participation - avoid multiple submissions
$sql = "SELECT *
    FROM {assign} a
    JOIN {assign_submission} s ON a.id = s.assignment
    WHERE a.course = ? AND s.status = 'submitted' AND s.userid in ('$userIDString') AND a.allowsubmissionsfromdate < ? AND a.cutoffdate > ?
    GROUP BY s.userid,s.assignment";
$amountUsersTakenAssignment = $DB->get_records_sql($sql, [$courseId, time(), time()]);


//Average Assignment Participation
//Count Submissions
$sql = "SELECT *
    FROM {assign} a
    JOIN {assign_submission} s ON a.id = s.assignment
    WHERE a.course = ? AND s.status = 'submitted' AND s.userid in ('$userIDString') AND a.allowsubmissionsfromdate < ?
    GROUP BY s.userid,s.assignment";
$amountUsersTakenAssignments = $DB->get_records_sql($sql, [$courseId, time()]);

$amountUTAs = count($amountUsersTakenAssignments);

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
//[[from, to, AverageGrade, Average Time until Deadline, date, stdgrade, stdtime, amount of Students taken A]]
$assignmentGradesO = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $assignmentGradesO[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0,0,0,0);
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

    //Read Amount Students Taken Assignment
    $sql = "SELECT DISTINCT userid
        FROM {assign} a
        JOIN {assign_submission} s ON a.id = s.assignment
        WHERE a.course = ?  AND s.userid in ('$userIDString')
        AND s.status = 'submitted'
        AND a.allowsubmissionsfromdate >= ? AND a.allowsubmissionsfromdate < ?";
    $amountStudentsTA = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $assignmentGradesO[$key][7] = count($amountStudentsTA);


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

    $assignmentGradesO[$key][4] = date("d.m.y", $week[0]);

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
    <!--Average Quiz Time-->
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        // Load the Visualization API and the corechart package.
        google.charts.load('current', {packages: ['bar']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);
        google.charts.setOnLoadCallback(drawChartI);
        google.charts.setOnLoadCallback(drawChartII);
        google.charts.setOnLoadCallback(drawChartIII);
        google.charts.setOnLoadCallback(drawChartIV);
        google.charts.setOnLoadCallback(drawChartV);

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
                title:'Quiz Grades',
                subtitle:'Average quiz grade with the corresponding standard deviation',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                legend: { position: 'top', alignment:'top' },
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
                ['Date', 'Average Time', 'Standard Deviation'],
                <?php
                foreach ($quizGradesO as $key => $entry ){
                    echo "['".$quizGradesO[$key][4]."',".$quizGradesO[$key][3].",".$quizGradesO[$key][6]."],";
                }
                ?>
            ]);


            // Set chart options
            var options = {
                title:'Quiz Time',
                subtitle:'Average time needed for a quiz (in minutes)',
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
                ['Date', 'Average Grade', 'Standard Deviation'],
                <?php
                foreach ($assignmentGradesO as $key => $entry ){
                    echo "['".$assignmentGradesO[$key][4]."',".$assignmentGradesO[$key][2].",".$assignmentGradesO[$key][5]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Assignment Grades',
                subtitle:'Average assignment grade with the corresponding standard deviation',
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
                ['Date', 'Average Time Before Deadline', 'Standard Deviation'],
                <?php
                foreach ($assignmentGradesO as $key => $entry ){
                    echo "['".$assignmentGradesO[$key][4]."',".$assignmentGradesO[$key][3].",".$assignmentGradesO[$key][6]."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Assignment Time',
                subtitle:'Average time of submission before deadline (in hours)',
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

        function drawChartIV() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Number of Students',''],
                <?php
                foreach ($assignmentGradesO as $key => $entry ){
                    echo "['".$assignmentGradesO[$key][4]."',".$assignmentGradesO[$key][7].","."0"."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Assignment Participation',
                subtitle:'Number of students that have taken an assignment',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Number of Students',
                    viewWindow:{
                        max:<?php echo count($records) ?>,
                    }
                }
            };

            options.colors = [
                '#001F3F',
                'white',
                '#F77F00',
                '#FCBF49',
                '#EAE2B7',
            ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_assignment_participation'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }

        function drawChartV() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Number of Students',''],
                <?php
                foreach ($quizGradesO as $key => $entry ){
                    echo "['".$quizGradesO[$key][4]."',".$quizGradesO[$key][7].","."0"."],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                title:'Quiz Participation',
                subtitle:'Number of students that have taken a quiz',
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:45,
                },
                vAxis: {
                    title: 'Number of Students',
                    viewWindow:{
                        max:<?php echo count($records) ?>,
                    }
                }
            };

            options.colors = [
                '#4D9DE0',
                'white',
                '#E1BC29',
            ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_quiz_participation'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }
    </script>


    <!-- Heading View -->
    <div style="position: fixed;top: 70px; left:125px; font-family: Arial; z-index: 4">
        <h2>Performance</h2>
    </div>




    <div style="font-family:Arial; right: 15% ;
    position: absolute;width:70%; top:140px;  display: flex; flex-wrap: wrap; justify-content: space-between;">


        <!-- Average Quiz Grade -->
        <div class="gr">
            <div style="font-family:Arial; display: flex;  justify-content:space-between; height: 140px">
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
            </div>

            <div id="chart_average_quiz_grades" class="divgraph" ></div>
            <!-- Set Max Quiz Grade -->
            <div>
                <form action="<?php echo $CFG->wwwroot.'/local/analytics/grades_time.php?courseid='.$course_id ?>" method="post">
                    <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                        <div class="tooltip">
                            <span class="tooltiptext" style="width: 200px">Set the maximum quiz grade to be achieved.</span>
                            <input class="inputfield" type="number" min="1" name="quizMaxGrade" placeholder="Max Quiz Grade"
                                   style="font-family: Arial;width:180px; ">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Average Assignment Grade -->
        <div class="gr">
            <div style="font-family:Arial; display: flex;  justify-content:space-between;height: 140px">
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

            <div id="chart_average_assignment_grades" class="divgraph"></div>
            <!-- Set Max Assignment Grade -->
            <div>
                <form action="<?php echo $CFG->wwwroot.'/local/analytics/grades_time.php?courseid='.$course_id ?>" method="post">
                    <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                        <div class="tooltip">
                            <span class="tooltiptext" style="width: 220px">Set the maximum assignment grade to be achieved.</span>
                            <input class="inputfield" type="number" min="1" name="assignmentMaxGrade" placeholder="Max Assignment Grade"
                                   style="font-family: Arial;width:180px; ">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Average Quiz Time -->
        <div class="gr" ">
            <div id="chart_average_quiz_time" class="divgraph"></div>
        <div style="height: 20px; width: 1px"></div>
        </div>


        <!-- Average Assignment Time -->
        <div class="gr">
            <div id="chart_average_assignment_time" class="divgraph"></div>
            <div style="height: 20px; width: 1px"></div>
        </div>

        <!-- Quiz Participation -->
        <div class="gr">
            <div id="chart_quiz_participation" class="divgraph"></div>
        </div>

        <!-- Assignment Participation -->
        <div class="gr">
            <div id="chart_assignment_participation" class="divgraph"></div>
        </div>
    </div>


    </body>
<?php