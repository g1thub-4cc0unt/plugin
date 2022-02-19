<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>

        img.imageclass {
            height: 25px; width: 25px;object-fit: contain;
        }

        /*Input Fields via https://doodlenerd.com/html-control/css-textbox-generator */
        .inputfield {
            padding: 5px;
            font-size: 16px;
            border-width: 1px;
            border-color: #3B3C3B;
            background-color: #FFFFFF;
            color: #000000;
            border-style: solid;
            border-radius: 0px;
        }
        .inputfield:focus {
            outline:none;
        }

        span.blueColor {
            color:#004C93;
            font-family:Arial;
            font-size: 16px;
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

        /* Sidebar https://stackoverflow.com/a/63864082 with slight modifications */
        #sidebar{
            width: 65px;
            height: 100%;
            background-color:#3B3C3B;
            position: fixed;
            z-index: 2;
            top: 69px;
            transition: all .3s ease-in-out;
            overflow: hidden;
        }
        #sidebar:hover{
            width: 230px;
            z-index: 5;
        }

        #sidebar a {
            text-decoration: none;
            display: block;
            padding: 20px 20px;
            letter-spacing: 1px;
            font-size: 16px;
            font-weight: bold;
            font-family: Arial;
            width: 100%;
            white-space: nowrap;
            transition: all .2s ease-in-out;
        }
        #sidebar a span {
            top: 0;
            opacity: 0;
        }
        #sidebar:hover a span {
            color:white;
            opacity: 1;
            transition: all .2s ease-in-out;
        }

        #sidebar a:hover {
            background-color: #3B3C3B;
            width: 190px;
        }

        #sidebar a:hover,
        #sidebar a.active {
            background: #0074D9;
        }

        /* Tooltip https://www.w3schools.com/howto/howto_css_tooltip.asp with slight modifications */
        /* Tooltip container */
        .tooltip {
            position: relative;
            display: inline-block;
        }

        /* Tooltip text */
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 120px;
            background-color: #555;
            color: #fff;
            text-align: center;
            padding: 5px 0;
            border-radius: 6px;

            /* Position the tooltip text */
            position: absolute;
            z-index: 3;
            bottom: 125%;
            left: 50%;
            margin-left: -60px;

            /* Fade in tooltip */
            opacity: 0;
            transition: opacity 0.3s;
        }

        /* Tooltip arrow */
        .tooltip .tooltiptext::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #555 transparent transparent transparent;
        }

        /* Show the tooltip text when you mouse over the tooltip container */
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Logout Button*/
        .logout a:hover {
            background-color: #001f3f;
            transition: all .2s ease-in-out;
        }

    </style>
</head>
<?php
require_once(__DIR__. "/../../config.php");
require_once(__DIR__. "/db/access.php");
require_once(__DIR__. "/functions.php");

global $DB;
global $CFG;

//Read Course Information
$course_id = required_param("courseid", PARAM_INT);

//Plugin User must be logged in and Role EditingTeacher/Teacher/Admin AND Course must exist
require_login();
if (!($DB->record_exists('course', array('id' => $course_id))) OR !(has_capability('local/analytics:view', CONTEXT_COURSE::instance($course_id)))){
    redirect("$CFG->wwwroot");
}
$course = getCourseInfo($course_id);

$startDate = $course -> startDate;
$startDateEpoch = $course -> startDateEpoch;
$endDateEpoch = $course -> endDateEpoch;
$courseId = $course -> id;
$course_name = $course -> name;

$url = new moodle_url($CFG->wwwroot."/local/analytics/");




?>
<body style="background-color:#EEEEEE">

<!-- Heading -->
<div style="width:100%;height:61px;position: absolute;background-image: linear-gradient(to right, #001f3f, #0074D9);
        display: flex;
        flex-direction: row;
        justify-content: center;
        z-index: 2;
        position: fixed">

    <div style="align-self:center">
        <h1 style="color:white;font-family:arial;">Course: <?php echo $course_name ?></h1>
    </div>
</div>


<!--scrolling no visible on top-->
<div style="width:100%; height:61px; top: -50px; position: fixed; background-color: #EEEEEE; z-index: 1">

</div>

<!-- Return Back to Course -->
<div style="right:15px;top:23px; position: fixed; z-index: 5"  class="logout">
    <a href="<?php echo (new moodle_url('/course/view.php', ['id' => $course_id]));?>" style="height: 34px; width: 34px; border-radius: 10px; display: inline-block">
        <img src="./icons/google/outline_logout_white_48dp.png" style="height: 32px; width: 32px;">
    </a>
</div>


<!-- Menu Buttons -->

<div id="sidebar">
    <a href="<?php echo $url."index.php?courseid=".$course_id ?>">
        <img src="./icons/google/outline_home_white_48dp.png" class="imageclass">
        <span>Course Overview</span>
    </a>

    <a href="<?php echo $url."grades_time.php?courseid=".$course_id ?>">
        <img src="./icons/google/outline_assessment_white_48dp.png" class="imageclass">
        <span>Performance</span>
    </a>

    <a href="<?php echo $url."material_usage.php?courseid=".$course_id ?>">
        <img src="./icons/google/outline_assessment_white_48dp.png" class="imageclass">
        <span>Material Usage</span>
    </a>


    <a href="<?php echo $url."students_overview.php?courseid=".$course_id ?>">
        <img src="./icons/google/outline_groups_white_48dp.png"class="imageclass">
        <span>Students</span>
    </a>


    <a href="<?php echo $url."notes.php?courseid=".$course_id ?>">
        <img src="./icons/google/outline_description_white_48dp.png" class="imageclass">
        <span>Notes</span>
    </a>

    <a href="<?php echo $url."faq.php?courseid=".$course_id ?>">
        <img src="./icons/google/outline_help_outline_white_48dp.png" class="imageclass">
        <span>FAQ</span>
    </a>

</div>

