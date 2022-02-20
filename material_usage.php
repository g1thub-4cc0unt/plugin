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

$PAGE->set_url(new moodle_url("/local/analytics/material_usage.php"));



?>


    <!-- Layout -->
    <head>
        <title>Material Usage</title>
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
    $sql = "SELECT DISTINCT userid
        FROM {logstore_standard_log} 
        WHERE courseid = ? AND userid IN ('$userIDString') 
        AND action='viewed' AND component = 'mod_resource' AND target = 'course_module' AND objectid = ?";
    $resourceViewed = $DB->get_records_sql($sql, [$courseId,$resource->id]);
    $resourceViewed = count($resourceViewed);
    $resource -> name = substr($resource -> name, 0,30);
    $resource -> views = (($resourceViewed)/count($records))*100;
    $rv = $resource -> views;
    if ($rv > 75){
        $resource -> color = '#001F3F';
    }
    elseif ($rv > 50){
        $resource -> color = '#D62828';
    }
    elseif ($rv > 25){
        $resource -> color = '#F77F00';
    }
    elseif($rv >= 0){
        $resource -> color = '#FCBF49';
    }

    //#EAE2B7

    /*    if ($rv > 75){
        $resource -> color = '#1e3d59';
    }
    elseif ($rv > 50){
        $resource -> color = '#f5f0e1';
    }
    elseif ($rv > 25){
        $resource -> color = '#ffc13b';
    }
    elseif($rv >= 0){
        $resource -> color = '#ff6e40';
    }
*/



    $resourcesToPrint[] = $resource;
    $resourcesViewed += $resourceViewed;
}

$resourcesViewed = ($resourcesViewed/($amountResources*count($records)))*100;

usort($resourcesToPrint, function ($a, $b) {
    return ($a->views <= $b->views);});



?>
    <!-- Resources viewed -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart','bar']});
        google.charts.setOnLoadCallback(drawChart);

        function drawChart() {
            var data = google.visualization.arrayToDataTable([
                ['Resource', 'Percentage',{role: 'style'}],
                <?php
                foreach($resourcesToPrint as $resource){
                    echo "['".$resource->name."',".round($resource->views,2).",'".$resource->color."'],";
                }
                ?>
            ]);

            var options = {
                title: 'Resource Views',
                chartArea: {width: '50%', height:'90%'},
                legend: { position: 'top', alignment:'center' },
                chart: { title: 'Resource Views',
                    subtitle: ' ' },
                bars: 'horizontal', // Required for Material Bar Charts.
                hAxis: {
                    viewWindow:{
                        max:100,

                        title: 'Percent',
                    }
                }
            };

            var chart = new google.visualization.BarChart(document.getElementById('chart_material_usage'));

            chart.draw(data, options);
        };
    </script>

    <!-- Heading View -->
    <div style="position: fixed;top: 70px; left:125px; font-family: Arial; z-index: 4">
        <h2>Material Usage</h2>
    </div>

    <!-- Forum Read - Resources Viewed - Percentage - Notes - Graph-->

    <div style=" top:140px; position: absolute; right:15%;display: flex; height: 82%; width:70%; justify-content:space-between;">


        <div style="width:46%; ">
            <!-- Resources Viewed -->
            <div style="width:300px;  margin: 0 auto;text-align:center; background-color: white; border-radius: 15px; padding: 10px 10px;">
                <span class="blackColor">Average Resource Views:</span> <span style="color:#004C93;font-family:Arial;"><?php echo round($resourcesViewed,2)."%" ?></span>
            </div>
            <div style="height: 20px; width: 1px"></div>
            <div id="chart_material_usage" style="height:95%;width: 100%"></div>
        </div>



        <div style=" width:46%; height: 95% ">
            <div style="width:300px; margin: 0 auto;text-align:center; background-color: white; border-radius: 15px; padding: 10px 10px;">
                <span class="blackColor">Average Readings Per Post:</span> <span style="color:#004C93;font-family:Arial;"><?php echo round($forumViewed,2)."%" ?></span>
            </div>
            <div style="height: 20px; width: 1px"></div>
            <div style="overflow-y: scroll; width:100%; height: 100%">
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
    </div>

    </body>
<?php

