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


        /* https://www.w3schools.com/howto/howto_js_popup_form.asp with modifications*/

        /* Button used to open the contact form - fixed at the bottom of the page */
        .open-button {
            background-color: #555;
            color: white;
            padding: 16px 20px;
            border: none;
            cursor: pointer;
            opacity: 0.8;
            position: fixed;
            bottom: 23px;
            right: 28px;
            width: 280px;
        }

        /* The popup form - hidden by default */
        .form-popup {
            display: none;
            position: fixed;
            top: 100px;
            right: 15px;
            border: 3px solid #f1f1f1;
            z-index: 9;
        }

        /* Add styles to the form container */
        .form-container {
            max-width: 300px;
            padding: 10px;
            background-color: white;
        }

        /* Full-width input fields */
        .form-container input[type=text], .form-container input[type=textfield] {
            width: 100%;
            padding: 15px;
            margin: 5px 0 22px 0;
            border: none;
            background: #f1f1f1;


        }

        /* When the inputs get focus, do something */
        .form-container input[type=text]:focus, .form-container input[type=password]:focus {
            background-color: #ddd;
            outline: none;
        }

        /* Set a style for the submit/login button */
        .form-container .btn {
            background-color: #479152;
            color: white;
            padding: 16px 20px;
            border: none;
            cursor: pointer;
            width: 100%;
            margin-bottom:10px;
            opacity: 0.8;
        }

        /* Add a red background color to the cancel button */
        .form-container .cancel {
            background-color: #F26157;
        }

        /* Add some hover effects to buttons */
        .form-container .btn:hover, .open-button:hover {
            opacity: 1;
        }

        .buttonSave{
            background-color: #479152;
            border: none;
            color: white;
            font-family: Arial;
            padding: 4% 8%;
            cursor: pointer;
            text-align: center;
            font-size: 16px;
            border-radius: 15px;
        }
        .buttonSave:hover{
            transition: all .2s ease-in-out;
            background-color: #28502E;
        }

        .buttonDelete{
            background-color: #F26157;
            border: none;
            color: white;
            font-family: Arial;
            padding: 4% 8%;
            cursor: pointer;
            text-align: center;
            font-size: 16px;
            border-radius: 15px;
        }
        .buttonDelete:hover{
            transition: all .2s ease-in-out;
            background-color: #A54657;
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
if (!isset($_GET["courseid"])){
    redirect("$CFG->wwwroot");
}
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


//Create Note -Top RightButton
if(isset($_POST["naction"]) AND $_POST["naction"] == "ncreate" ) {
    $record = new stdClass();
    $record->notetext = $_POST["ntext"];
    $record->date = time();
    $record->courseid = $courseId;

    $nsql = 'SELECT u.id as uid, u.firstname, u.lastname 
            FROM {course} c
            JOIN {context} ct ON c.id = ct.instanceid
            JOIN {role_assignments} ra ON ra.contextid = ct.id
            JOIN {user} u ON u.id = ra.userid
            JOIN {role} r ON r.id = ra.roleid
            WHERE ra.roleid = 5 AND c.id = ?';
    $nrecords = $DB->get_records_sql($nsql, [$courseId]);

    if(isset($_GET["userid"])){
        $userID = $_GET["userid"];
        $nstudent = getStudent($userID, $courseId);
        foreach ($nrecords as $user){
            if (($user->uid) == $userID){
                $nstudent -> firstname = $user -> firstname;
                $nstudent -> lastname = $user -> lastname;
            }
        }
        $record->name = ($nstudent->firstname) . " " . ($nstudent->lastname);
    }else{
        $record->name = "";
    }

    $record->context = $_POST["ntag"];
    $DB->insert_record("local_analytics_notes", $record);
}

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

<!-- Create note -->
<div style="right:15px;top:100px; position: fixed; z-index: 5">
    <a style="position: relative;cursor: pointer;" onclick="openForm(); ">
        <img src="./icons/google/outline_add_circle_outline_black_48dp.png" height="32px" width="32px">
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

<!-- Add Note - Button -->
<?php
if(isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
    $nurl = "https://";
else
    $nurl = "http://";

$nurl.= $_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];

?>


<!-- Source https://www.w3schools.com/howto/howto_js_popup_form.asp -->
<script>
    function openForm() {
        document.getElementById("myForm").style.display = "block";
    }

    function closeForm() {
        document.getElementById("myForm").style.display = "none";
    }
</script>


<div class="form-popup" id="myForm">
    <form action=<?php echo $nurl ?>  method="post" class="form-container">
        <h2>Create Note</h2>

        <input type="hidden" name="naction" value="ncreate">
        <label for="ntag"><b>Note Tag</b></label>
        <input list="predefinedtags" type="text" placeholder="Note Tag" name="ntag" required>

        <label for="ntext"><b>Note Text</b></label>
        <input type="text" placeholder="Note Text" name="ntext" required>

        <button type="submit" class="btn">Create</button>
        <button type="button" class="btn cancel" onclick="closeForm()">Close</button>

        <datalist id="predefinedtags">
            <option value="Activity">
            <option value="Forum">
            <option value="General">
            <option value="Grade">
            <option value="Material Usage">
            <option value="Performance">

        </datalist>
    </form>
</div>


