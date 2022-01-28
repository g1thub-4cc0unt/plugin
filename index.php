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

$PAGE->set_url(new moodle_url("/local/analytics/index.php"));
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
            color:black;
            font-weight:bold;
            font-family:Arial;
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
if (count($records) > 0){
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
$userIDs = join("','",$userIDs);

//Tagged Students ToDo
$students = array_slice($records,0,10);

//Number of inactive Students
$sql ="SELECT DISTINCT l.userid
       FROM mdl_logstore_standard_log l 
       WHERE  l.courseid = ? AND l.timecreated > ? AND l.userid IN ('$userIDs')
       AND l.action ='viewed' AND l.target ='course'";
$activeUsers = $DB->get_records_sql($sql, [$courseId, time()-604800]);

$amountInactiveUsers = count($records)-count($activeUsers);


//Active Students Graph  86400 = 1 day
$dateNow = date("m/d/Y", time());
$dateNowEpoch = date_create($dateNow)->format('U');

//[[from, to, amount]]
$pastDaysActiveUsers = array();
for ($i = 1; $i <= 7; $i++) {
    $pastDaysActiveUsers[] = array($dateNowEpoch-(86400*(7-$i)),$dateNowEpoch-(86400*((7-1)-$i)),999);
    //echo date("m/d/Y H:i:s",$pastDaysActiveUsers[$i-1][0])." ".date("m/d/Y H:i:s",$pastDaysActiveUsers[$i-1][1])."<br> ";
}

$xAxisDates = array();
$yAxisAmountStudents = array();
foreach ($pastDaysActiveUsers as $key => $arr) {
    $sql = "SELECT DISTINCT userid
            FROM {logstore_standard_log}
            WHERE action='viewed' AND target='course'
            AND courseid=? AND userid IN ('$userIDs') 
            AND timecreated > ? AND timecreated < ?";
    $activeUsers = $DB->get_records_sql($sql, [$courseId, $arr[0], $arr[1]]);
    $pastDaysActiveUsers[$key][3] = count($activeUsers);

    $xAxisDates[] = date ("d.m.Y",$arr[0]);
    $yAxisAmountStudents[] = count($activeUsers);
}

$graph = new graph();
$graph->bar( range(0,6), array_reverse($yAxisAmountStudents), ['label'=>'Active Students']);
$graph->axes([0,6,0,max($yAxisAmountStudents)]);
$graph->xlabel("Days before today");
$graph->legend( $legend = true );
$graph->title("Active Students past days");


//New Topics and Posts per week 604800 = 1 week
$dateNow = date("m/d/Y", time());
$dateNowEpoch = date_create($dateNow)->format('U');

$courseDurationWeeks = ($endDateEpoch - $startDateEpoch)/604800;

//[[from, to, amountPosts, amountTopics]]
$weeks = array();
for ($i = 1; $i <= $courseDurationWeeks+1; $i++) {
    $weeks[] = array($startDateEpoch+(604800*($i-1)),$startDateEpoch+(604800*$i),999,999);

}

$xAxisDatesPosts = array();
$yAxisAmountPosts = array();

$xAxisDatesTopics = array();
$yAxisAmountTopics = array();
foreach ($weeks as $key => $week) {
    //Read Amount of Posts per Week
    $sql = "SELECT p.* 
            FROM {forum_discussions} fd
            JOIN {forum_posts} p ON fd.id = p.discussion
            WHERE fd.course = ? AND p.created > ? AND p.created < ? AND p.subject LIKE 'Re:%' ";
    $aPosts = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $weeks[$key][2] = count($aPosts);

    $xAxisDatesPosts[] = date("m.d.Y",$week[0]);
    $yAxisAmountPosts[] = count($aPosts);

    //Read Amount of Topics per Week
    $sql = "SELECT p.*
            FROM {forum_discussions} fd
            JOIN {forum_posts} p ON fd.id = p.discussion
            WHERE fd.course = ? AND p.created > ? AND p.created < ? AND p.subject NOT LIKE 'Re:%' ";
    $aTopics = $DB->get_records_sql($sql, [$courseId, $week[0], $week[1]]);
    $weeks[$key][3] = count($aTopics);

    $xAxisDatesTopics[] = date("m.d.Y",$week[0]);
    $yAxisAmountTopics[] = count($aTopics);

    //echo date("m/d/Y H:i:s",$week[0])." ".date("m/d/Y H:i:s",$week[1])." New Posts: ". count($aPosts)." New Topics: ".count($aTopics)."<br>";
}

/*
$graphTP = new graph();
$graphTP->bar( $xAxisDatesTopics, $yAxisAmountTopics, ['label'=>'New Topics']);
$graphTP->bar( $xAxisDatesPosts, $yAxisAmountPosts, ['label'=>'New Posts']);
$graphTP->xlabel( 'Week' );
$graphTP->axes([1,$courseDurationWeeks,0,5]);
$graphTP->legend( $legend = true );
$graphTP->title("New Topics and Posts per week");
*/

//Graph Posts
$graphP = new graph();
$graphP->bar( range(1,ceil($courseDurationWeeks)), $yAxisAmountPosts, ['label'=>'New Posts']);
$graphP->xlabel( 'Week' );
$graphP->axes([1,ceil($courseDurationWeeks),0,max($yAxisAmountPosts)]);
$graphP->legend( $legend = true );
$graphP->title("New Posts per week");

//Graph Topics

$graphT = new graph();
$graphT->bar(range(1,ceil($courseDurationWeeks)), $yAxisAmountTopics, ['label'=>'New Topics']);
$graphT->xlabel( 'Week' );
$graphT->axes([1,ceil($courseDurationWeeks),0,max($yAxisAmountTopics)]);
$graphT->legend( $legend = true );
$graphT->title("New Topics per week");

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

    <!-- General Information -->

    <div style="top: 9%; right: 3%; position: absolute; height: 7%; width: 90%;  ">
        <div style="font-family:Arial;  display: flex;  justify-content:space-around; align-items: center">

            <!-- Coursestart -->
            <div style="background-color:white;
            text-align: center;
            padding: 1% 3%;
            border-radius: 15px ">
                <p>
                    <span class="blackColor"> Coursestart:</span> <span class ="blueColor"><?php echo $startDate ?></span>
                </p>
            </div>

            <!-- Enrolled Students -->
            <div style="background-color:white;
            text-align: center;
            padding: 1% 3%;
            border-radius: 15px ">
                <p>
                    <span class="blackColor"> Enrolled Students:</span> <span class ="blueColor"><?php echo count($records) ?></span>
                    <br>
                    <span class="blackColor"> Inactive Students:</span> <span class ="blueColor"><?php echo $amountInactiveUsers ?></span>
                </p>
            </div>

            <!-- Tagged Students -->
            <div style="background-color:white;
                    text-align: center;
                    padding: 1% 3%;
                    border-radius: 15px ">
                <p>
                    <span class="blackColor"><img src="./icons/Pin.png" width="20" height="20">Tagged Students:</span>
                <hr>
                <span class ="blueColor">
                <?php
                $studentAnalyticsUrl = new moodle_url($CFG->wwwroot."/local/analytics/student_analytics.php");
                foreach ($taggedStudents as $taggedStudents) {
                ?>
                <a href=" <?php echo $studentAnalyticsUrl. '?course='. $course_name."&userid=".$taggedStudents->userid ?> " ?>
                <?php
                echo ($taggedStudents->name)."</br>";
                } ?>
                    </a>
            </span>
                </p>
            </div>
        </div>
    </div>


    <div style="font-family:Arial;overflow-y: scroll;top:30%; right: 3% ;
    position: absolute;width:90%; height:70%; top:30%;  display: flex; gap: 10px 40px; flex-wrap: wrap; justify-content:space-evenly;">


        <!-- Average Student Activity -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graph->output_gd_png_base64( )?>">
        </div>

        <!-- Last 3 Notes -->
        <div style="
            width:38%; height: 50%; text-align: justify;  padding: 1% 1%;
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

        <!-- New Topic per week -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphT->output_gd_png_base64( )?>">
        </div>

        <!-- New Posts per week -->
        <div style="width:40%; height: 80%;">
            <img style='height: 100%; width: 100%; object-fit: contain' src="<?php echo $graphP->output_gd_png_base64( )?>">
        </div>





    </div>


    </body>
<?php
