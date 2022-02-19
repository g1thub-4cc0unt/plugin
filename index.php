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

$PAGE->set_url(new moodle_url("/local/analytics/index.php"));
?>


<!-- Layout -->
<head>
    <title>Course Overview</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        span.blueColor {
            color:#004C93;
            font-family:Arial;
        }
        span.blackColor {
            color:black;
            font-weight:bold;
            font-family:Arial;
        }
    </style>
</head>



<?php

//Read Course Information
$course_id = required_param("courseid", PARAM_INT);
$url45 = new moodle_url($CFG->wwwroot."/local/analytics/index.php?courseid=".$course_id);

//Fix Broken Images
if (isset($_GET["r"])) {
    redirect($url45);
}
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
$userIDs = join("','",$userIDs);



$inactiveGT = 0;
//Read Number of Days until a student is considered inactive
if (isset($_POST['daysInactive'])) {
    $inactiveGT = time() - (86400 * $_POST['daysInactive']);

    $record = new stdClass();
    $record->courseid = $course_id;
    $record->name = "daysInactive";
    $record->setting = $_POST['daysInactive'];
    $DB->delete_records_select("local_analytics_settings", 'courseid= ? AND name = ?', [$courseId, "daysInactive"]);
    $DB->insert_record("local_analytics_settings", $record);
}else{
        $sql ="SELECT *
           FROM {local_analytics_settings}
           WHERE courseid = ? AND name = ?";
        $day = $DB->get_record_sql($sql,[$courseId, "daysInactive"]);
        if (!is_null($day->setting)){
            $inactiveGT = time() - (86400 * $day->setting);
        }
}


//Number of inactive Students
$sql ="SELECT DISTINCT l.userid
       FROM mdl_logstore_standard_log l 
       WHERE  l.courseid = ? AND l.timecreated > ? AND l.userid IN ('$userIDs')
       AND l.action ='viewed' AND l.target ='course'";
$activeUsers = $DB->get_records_sql($sql, [$courseId, $inactiveGT]);

$amountInactiveUsers = count($records)-count($activeUsers);


//Active Students Graph  86400 = 1 day & avoid empty graph

$dateNow = date("m/d/Y", time());
$dateNowEpoch = date_create($dateNow)->format('U');
if ($dateNowEpoch > $endDateEpoch){
    $dateNowEpoch = $endDateEpoch;
}
//[[from, to, amount, date]]
$pastDaysActiveUsers = array();
for ($i = 1; $i <= 7; $i++) {
    $pastDaysActiveUsers[] = array($dateNowEpoch-(86400*(7-$i)),$dateNowEpoch-(86400*((7-1)-$i)),0,0);
    //echo date("m/d/Y H:i:s",$pastDaysActiveUsers[$i-1][0])." ".date("m/d/Y H:i:s",$pastDaysActiveUsers[$i-1][1])."<br> ";
}

foreach ($pastDaysActiveUsers as $key => $arr) {
    $sql = "SELECT DISTINCT userid
            FROM {logstore_standard_log}
            WHERE action='viewed' AND target='course'
            AND courseid=? AND userid IN ('$userIDs') 
            AND timecreated > ? AND timecreated < ?";
    $activeUsers = $DB->get_records_sql($sql, [$courseId, $arr[0], $arr[1]]);
    $pastDaysActiveUsers[$key][2] = count($activeUsers);
    $pastDaysActiveUsers[$key][3] = date ("D d.m",$arr[0]);
}


//Active Students Graph  604800 = 1 Week & avoid empty graph

if ($endDateEpoch > time()) {
    $courseDurationWeeks = (time() - $startDateEpoch) / 604800;
}else{
    $courseDurationWeeks = ($endDateEpoch - $startDateEpoch)/604800;
}

//[[from, to, amount, date]]
$pastWeeksActiveUsers = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $pastWeeksActiveUsers[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0);
    //echo date("m/d/Y H:i:s",$pastWeeksActiveUsers[$i-1][0])." ".date("m/d/Y H:i:s",$pastWeeksActiveUsers[$i-1][1])."<br> ";
}

foreach ($pastWeeksActiveUsers as $key => $arr) {
    $sql = "SELECT DISTINCT userid
            FROM {logstore_standard_log}
            WHERE action='viewed' AND target='course'
            AND courseid=? AND userid IN ('$userIDs') 
            AND timecreated > ? AND timecreated < ?";
    $activeUsers = $DB->get_records_sql($sql, [$courseId, $arr[0], $arr[1]]);
    $pastWeeksActiveUsers[$key][2] = count($activeUsers);
    $pastWeeksActiveUsers[$key][3] = date("d.m.y",$arr[0]);
}





//New Topics and Posts per week 604800 = 1 week
$dateNow = date("m/d/Y", time());
$dateNowEpoch = date_create($dateNow)->format('U');

if ($endDateEpoch > time()) {
    $courseDurationWeeks = (time() - $startDateEpoch) / 604800;
}else{
    $courseDurationWeeks = ($endDateEpoch - $startDateEpoch)/604800;
}
//[[from, to, amountPosts, amountTopics,date]]
$postTopics = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $postTopics[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),0,0,0);

}

foreach ($postTopics as $key => $week) {
    //Read Amount of Posts per Week
    $sql = "SELECT p.* 
            FROM {forum_discussions} fd
            JOIN {forum_posts} p ON fd.id = p.discussion
            WHERE fd.course = ? AND p.created > ? AND p.created < ? AND p.subject LIKE 'Re:%' ";
    $aPosts = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $postTopics[$key][2] = count($aPosts);
    $postTopics[$key][4] = date("d.m.y",$week[0]);


    //Read Amount of Topics per Week
    $sql = "SELECT p.*
            FROM {forum_discussions} fd
            JOIN {forum_posts} p ON fd.id = p.discussion
            WHERE fd.course = ? AND p.created > ? AND p.created < ? AND p.subject NOT LIKE 'Re:%' ";
    $aTopics = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $postTopics[$key][3] = count($aTopics);


    //echo date("m/d/Y H:i:s",$week[0])." ".date("m/d/Y H:i:s",$week[1])." New Posts: ". count($aPosts)." New Topics: ".count($aTopics)."<br>";
}


//Last 3 Notes
$sql ="SELECT *
       FROM {local_analytics_notes}
       WHERE courseid = ?";
$notes = $DB->get_records_sql($sql, [$courseId]);

foreach($notes as $key => $note){
    $notes[$key]->date = date('d/m/Y H:i:s', $note->date);
}
$notes = array_slice($notes, -3, 4);

//Read tagged Students
$sql ="SELECT *
       FROM {local_analytics_tagged}
       WHERE courseid = ?";
$taggedStudents = $DB->get_records_sql($sql, [$courseId]);

?>
    <!--Active Students past days -->
    <!--Load the AJAX API-->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">

        // Load the Visualization API and the corechart package.
        google.charts.load('current', {packages: ['bar']});

        // Set a callback to run when the Google Visualization API is loaded.
        google.charts.setOnLoadCallback(drawChart);
        google.charts.setOnLoadCallback(drawChartI);
        google.charts.setOnLoadCallback(drawChartII);

        // Callback that creates and populates a data table,
        // instantiates the pie chart, passes in the data and
        // draws it.
        function drawChart() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Active Students'],
                <?php
                foreach ($pastDaysActiveUsers as $key => $entry ){
                    echo "['".$pastDaysActiveUsers[$key][3]."'," .$pastDaysActiveUsers[$key][2]. "],";
                }
                ?>
            ]);


            /*    $pastDaysActiveUsers[$key][4] = date ("d.m.Y",$arr[0]);*/

            // Set chart options
            var options = {
                title:'Active Students',
                subtitle:'Number of active students in the last 7 days',
                legend: { position: 'none' },
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
                '#001f3f',
            ];


            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_active_students'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }

        function drawChartI() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Active Students'],
                <?php
                foreach ($pastWeeksActiveUsers as $key => $entry ){
                    echo "['".$pastWeeksActiveUsers[$key][3]."'," .$pastWeeksActiveUsers[$key][2]. "],";
                }
                ?>
            ]);


            /*    $pastDaysActiveUsers[$key][4] = date ("d.m.Y",$arr[0]);*/

            // Set chart options
            var options = {
                title:'Active Students',
                subtitle:'Number of active students per week',
                legend: { position: 'none' },
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
                '#0074D9',
            ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_active_students_weeks'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }

        function drawChartII() {

            // Create the data table.
            var data = google.visualization.arrayToDataTable([
                ['Date', 'Posts','Topics'],
                <?php
                foreach ($postTopics as $key => $entry ){
                    echo "['".$postTopics[$key][4]."',".$postTopics[$key][2]."," .$postTopics[$key][3]. "],";
                }
                ?>
            ]);

            // Set chart options
            var options = {
                theme: 'material',
                title:'Forum Activity',
                subtitle: 'Number of new posts and topics posted per week',
                legend:{position: 'top', alignment: 'top'},
                hAxis: {
                    slantedText:true,
                    slantedTextAngle:90,
                },
                vAxis: {
                    title: 'Number of Posts & Topics',
                }
            };
            options.colors = [
                '#3a394a',
                '#FF4136',
                ];

            // Instantiate and draw our chart, passing in some options.
            var chart = new google.charts.Bar(document.getElementById('chart_posts_topics'));
            chart.draw(data, google.charts.Bar.convertOptions(options));
        }
    </script>






    <!-- Heading View -->
    <div style="position: fixed;top: 70px; left:125px; font-family: Arial; z-index: 4">
        <h2>Course Overview</h2>
    </div>
    <!-- Graphs/Infos -->

    <div style="top: 140px; right: 15%; column-gap: 40px; position: absolute; width: 70%;
        display: flex;flex-wrap:wrap;align-content: space-between">

        <div style="column-gap: 40px;display: flex;flex-wrap:wrap;align-content: space-between;width: calc(100% - 250px)">
        <!-- Active Students last 7 days -->
        <div style="height: 400px;width: 500px;">
            <div id="chart_active_students" style="height: 350px;width: 500px;"></div>
        </div>

        <!-- Active Student past weeks -->
        <div style="height: 400px;width: 500px;">
            <div id="chart_active_students_weeks" style="height: 350px;width: 500px;"></div>
        </div>

        <!-- New Topic and Topics per week -->
        <div style="height: 400px;width: 500px;">
            <div id="chart_posts_topics" style="height: 350px;width: 500px;"></div>
        </div>

        <!-- Last 3 Notes -->
            <div style="
            width:490px; height: 330px; text-align: justify;  padding: 10px 5px;
            background-color:white; border-radius: 15px; overflow-y: scroll;
            font-family:Arial;word-wrap:break-word;">
                <?php
                echo'<span class="blackColor"> <br>Last Notes:</span><hr>';

                foreach (($notes) as $note) {
                    echo "<u>".($note->context)."</u>";
                    echo "<br>";
                    echo ($note->date);
                    echo "<br>";
                    echo ($note->name);
                    echo "<br>";
                    echo "<br>";
                    echo "".$note->notetext ."<br><hr><br>". "";
                }

                ?>

            </div>
        </div>
        <!--General Information -->
        <div style="font-family:Arial; width: 180px;height: 80%; display: flex; flex-direction: column; justify-content:space-around; row-gap:40px; align-items: center">

            <!-- Coursestart -->
            <div style="background-color:white;
            text-align: center;
             padding: 10px 5px;
            width: 180px;
            border-radius: 15px ">
                <p>
                    <span class="blackColor"> Coursestart:</span> <span class ="blueColor"><?php echo $startDate ?></span>
                </p>
            </div>

            <!-- Enrolled/Inactive Students -->
            <div style="background-color:white;
            text-align: center;
            padding: 10px 5px;
            width: 180px;
            border-radius: 15px ">


                <span class="blackColor"> Enrolled Students:</span> <span class ="blueColor"><?php echo count($records) ?></span>
                <br>
                <span class="blackColor"> Inactive Students:</span> <span class ="blueColor"><?php echo $amountInactiveUsers ?></span>
                <br>
                <form action="<?php echo $CFG->wwwroot.'/local/analytics/index.php?courseid='.$course_id ?>" method="post">
                    <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                        <div class="tooltip">
                            <span class="tooltiptext" style="width: 200px">Set the number of days from which a student is considered inactive.</span>
                            <input  class="inputfield" type="number" min="0" name="daysInactive" placeholder="Days Until Inactive"
                                    style="font-family: Arial;width:155px; ">

                        </div>
                    </div>
                </form>
            </div>

            <!-- Tagged Students -->
            <div style="background-color:white;
                    text-align: center;
                     padding: 10px 5px;
                     width: 180px;
                    border-radius: 15px ">
                <p>
                    <span class="blackColor"><img src="./icons/google/outline_bookmark_black_48dp.png" width="20" height="20">Bookmarked Students:</span>
                <hr>
                <span class ="blueColor">
                <?php
                $studentAnalyticsUrl = new moodle_url($CFG->wwwroot."/local/analytics/student_analytics.php");
                foreach ($taggedStudents as $taggedStudents) {
                ?>
                <a href=" <?php echo $studentAnalyticsUrl. '?courseid='. $course_id."&userid=".$taggedStudents->userid ?> " ?>
                <?php
                echo ($taggedStudents->name)."</br>";
                } ?>
                    </a>
            </span>
                </p>
            </div>
        </div>


    </div>
    </body>
<?php
