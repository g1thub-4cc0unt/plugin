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

$PAGE->set_url(new moodle_url("/local/analytics/material_usage.php"));



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
                padding: 4% 8%;
                cursor: pointer;
                text-align: center;
                font-size: 16px;
                border-radius: 15px;
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


//Read Posts Percentage
$sql = "SELECT p.*
    FROM {forum_posts} p
    JOIN {forum_discussions} d ON d.id = p.discussion
    AND d.course = ?
    ORDER BY p.discussion ASC";
$posts = $DB->get_records_sql($sql, [$courseId]);
$amountPosts = count($posts);
$postsRead = 0; //Overall Forum Read

$postsToPrint = array();

foreach ($posts as $post){
    $sql = "SELECT count(*) AS amountpostsread
    FROM {forum_read} r
    JOIN {forum_discussions} d ON d.id = r.discussionid
    AND d.course = ? AND r.userid IN ('$userIDString') AND r.postid = ?";

    $amountPostRead = $DB->get_record_sql($sql, [$courseId, $post->id]);
    $amountPostRead = $amountPostRead -> amountpostsread;
    $postsRead += $amountPostRead;

    $post -> postViewed = ($amountPostRead/count($records))*100;
    $postsToPrint[] = $post;
}

$forumViewed = ($postsRead/($amountPosts*count($records)))*100;

//Read Material Viewed Percentage
$sql = "SELECT * 
            FROM {resource} 
            WHERE course = ?";
$resources = $DB->get_records_sql($sql, [$courseId]);
$amountResources = count($resources);

$resourcesViewed = 0; //Overall Resources viewed
$resourcesToPrint = array();

foreach ($resources as $resource){
    $sql = "SELECT count(*) as amountviews
        FROM {logstore_standard_log} 
        WHERE courseid = ? AND userid IN ('$userIDString') 
        AND action='viewed' AND component = 'mod_resource' AND target = 'course_module' AND objectid = ?";
    $resourceViewed = $DB->get_record_sql($sql, [$courseId,$resource->id]);
    $resource -> views = (($resourceViewed -> amountviews)/count($records))*100;
    $resourcesToPrint[] = $resource;
    $resourcesViewed += $resourceViewed -> amountviews;
}

$resourcesViewed = ($resourcesViewed/($amountResources*count($records)))*100;



?>
    <!-- Forum Read - Forum Viewed - Percentage -->

    <div style="display:flex; position: absolute;right:5%; top: 10%;width: 90%;justify-content:space-evenly;" >
        <div style="text-align:center; background-color: white; border-radius: 15px; padding: 1% 1%;">
            <span class="blackColor">Resources Viewed:</span> <span style="color:#004C93;font-family:Arial;"><?php echo round($resourcesViewed,2)."%" ?></span>
        </div>
        <div style="text-align:center; background-color: white; border-radius: 15px; padding: 1% 1%;">
            <span class="blackColor">Forum Read:</span> <span style="color:#004C93;font-family:Arial;"><?php echo round($forumViewed,2)."%" ?></span>
        </div>
    </div>

    <div style=" top:18%; position: absolute; right:5%;display: flex; height: 82%; width:90%; justify-content:space-evenly;">


        <div style="overflow-y: scroll; width:40%;  ">

            <!-- Resources Viewed -->
            <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                <div style="width:25%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Name</span> </div>
                <div style="width:15%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Viewed</span> </div>
                <div style="width:10%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Type</span> </div>

            </div>

            <br>
            <hr>
            <br>

            <!-- List Resources -->

            <?php foreach($resourcesToPrint as $resource){ ?>


                <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">

                    <div style="width:25%;word-wrap:break-word;">
                        <?php echo $resource->name ?>
                    </div>

                    <div style="width:15%;">
                        <?php echo round($resource->views,2)."%" ?>
                    </div>

                    <div style="width:10%;">
                        <?php echo""; ?>
                    </div>


                </div>
                <br>
                <hr>
                <br>
            <?php } ?>
        </div>


        <div style="overflow-y: scroll; width:40%; ">


            <!-- Posts Viewed -->
            <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                <div style="width:25%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Subject</span> </div>
                <div style="width:15%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Read</span> </div>
                <div style="width:10%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Date</span> </div>
            </div>

            <br>
            <hr>
            <br>

        <!-- List Posts -->
        <?php foreach($postsToPrint as $post){ ?>
            <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">

                <div style="width:25%;word-wrap:break-word;">
                    <?php echo $post->subject ?>
                </div>

                <div style="width:15%;">
                    <?php echo round(($post->postViewed),2)."%"?>
                </div>

                <div style="width:10%;">
                    <?php echo date('d/m/Y H:i:s', $post->created) ?>
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
