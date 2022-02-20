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

$PAGE->set_url(new moodle_url("/local/analytics/faq.php"));

?>


<!-- Layout -->
<head>
    <title>FAQ</title>
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
        p{
           display:inline-block;
           background-color: white;
           width:90%;
           text-align: justify;
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

?>

<!-- Questions -->
    <div style="color:black;font-family:arial;position:absolute; width: 70%; left:15%; top:10%; text-align:center;margin:auto" >
        <h1>Current most frequently asked questions</h1>
        <br>
        <div style="display:inline-block; background-color: white; width:99%; padding: 1% 1%;">
            <h2>What graphs do I see on the student analytics page?</h2>
            <p>The student analytics page displays graphs showing what grades were achieved in a quiz/assignment and how long it took the student to complete the submissions. Only data for quizzes and assignments that were actually completed by the user are displayed.</p>
        </div>
        <br><br><br>
        <div style="display:inline-block; background-color: white; width:99%; padding: 1% 1%;">
            <h2>Can i change the colour or design of the Dashboard?</h2>
            <p>The Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
        </div>
        <br><br><br>
        <div style="display:inline-block; background-color: white; width:99%; padding: 1% 1%;">
            <h2>Is this project open source?</h2>
            <p>The Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet.</p>
        </div>
        <br><br><br>
        <div style="display:inline-block; background-color: white; width:99%; padding: 1% 1%;">
            <h2>Why does this plugin only work on Moodle?</h2>
            <p>The Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. Lorem ipsum dolor sit amet, consetetur sadipscing elitr, sed diam nonumy eirmod tempor invidunt ut labore et dolore magna aliquyam erat, sed diam voluptua. At vero eos et accusam et justo duo dolores et ea rebum. Stet clita kasd gubergren, no sea takimata sanctus est Lorem ipsum dolor sit amet. </p>
        </div>
    </div>

    </body>

<?php

