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

$PAGE->set_url(new moodle_url("/local/analytics/notes.php"));


//read Note from DB
function getNote($noteid) {
    global $DB;
    $sql = "SELECT *
            FROM {local_analytics_notes}
            WHERE id = ?";
    $note = $DB->get_record_sql($sql, [$noteid]);
    return $note;
}
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


//Needed in Notes HTML
$analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/notes.php");
$url = $analytics_url . "?course=". $course_name;

$noteToEdit = null;
if (isset($_GET['noteid'])) {
    $noteID = required_param("noteid", PARAM_INT);
    $noteToEdit = getNote($noteID);
}


$notes = null;
if (isset($_POST['sname'])){
    $sql ="SELECT *
           FROM {local_analytics_notes}
           WHERE courseid = ? AND (name LIKE ? )";
    echo "Hi";
    $notes = $DB->get_records_sql($sql,[$courseId, $_POST["sname"]."%"]);}

elseif (isset($_POST['scontext'])){
        $sql ="SELECT *
           FROM {local_analytics_notes}
           WHERE courseid = ? AND (context LIKE ?)";
        $notes = $DB->get_records_sql($sql,[$courseId,$_POST["scontext"]."%"]);
}else{
    $sql ="SELECT *
           FROM {local_analytics_notes}
           WHERE courseid = ?";
    $notes = $DB->get_records_sql($sql,[$courseId]);
}

//Edit Note
if (isset($_POST["action"]) AND ($_POST['noteid'] != 0)   AND $_POST["action"] == "create" ){
    $record = new stdClass();
    $record->id = $_POST["noteid"];
    $record->notetext = $_POST["notetext"];
    $record->courseid = $courseId;
    $record->date = time();
    $record->name = $_POST["name"];
    $record->context = $_POST["context"];

    $DB->update_record("local_analytics_notes", $record, $bulk=false);

    header("Refresh:0; $url");
}
//Create Note
elseif (isset($_POST["action"]) AND $_POST["action"] == "create" ){
    $record = new stdClass();
    $record->notetext = $_POST["notetext"];
    $record->date = time();
    $record->courseid = $courseId;
    $record->name = $_POST["name"];
    $record->context = $_POST["context"];
    $DB->insert_record("local_analytics_notes", $record);

    header("Refresh:0; $url");
}

//Delete Note
elseif (isset($_POST["action"]) AND $_POST["action"] == "delete" ){
    $DB->delete_records_select("local_analytics_notes", 'id= ?',[$_POST["noteid"]]);

    header("Refresh:0; $url");
}


?>
    <!-- Search Bars -->

    <div style="top: 9%; right: 25%; position: absolute; height: 7%; width: 50%; background-color:#3B3C3B ">
        <div style="font-family:Arial;  display: flex;  justify-content:space-around; align-items: center">
            <!-- Search by Name -->
            <div>
                <form class="example" action="<?php echo $url ?>" method="post" style="max-width:300px">
                    <input type="text" placeholder="Search By Name" name="sname">
                    <button>&#128269; </button>
                </form>
            </div>

            <!-- Search by Context -->
            <div>
                <form class="example" action="<?php echo $url ?>" method="post" style="max-width:300px">
                    <input type="text" placeholder="Search By Context" name="scontext">
                    <button>&#128269; </button>
                </form>
            </div>
        </div>
    </div>









    <div>
        <!-- Create/Edit Notes Button and Fields -->
        <div style="position: absolute; top: 22.5%; right: 20%; position: absolute;  width: 60%;">
            <form action="<?php echo $url ?>" method="post">
                <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="noteid" value="<?php if (isset($_GET['noteid']))  {echo $noteToEdit->id;} else echo 0;?>">
                    <div><input type="text" name="context" placeholder="Note Context"
                           value="<?php if (isset($_GET['noteid']))  {echo $noteToEdit->context;} ?>">
                    </div>
                    <div>
                    <input type="text" name="name" placeholder="Student Name"
                           value="<?php if (isset($_GET['noteid']))  {echo $noteToEdit->name;} ?>">
                    </div>
                    <div>
                    <textarea name="notetext" cols="30" rows="4"
                              placeholder="Note Text"><?php if (isset($_GET['noteid']))  {echo $noteToEdit->notetext;} ?></textarea>
                    </div>
                    <div>
                    <button class="button" type="submit" name="save">
                        <?php if (isset($_GET['noteid'])) {
                            echo "Save Note";
                        }
                        else{
                            echo "Create Note";
                        } ?>
                    </button>
                    </div>
                </div>
            </form>
        </div>

        <div style="overflow-y: scroll; width:60%; height:60%; top:40%; right: 20% ;position: absolute ">

            <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                <div style="width:10%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Context</span> </div>
                <div style="width:10%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Student Name</span> </div>
                <div style="width:25%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Text</span> </div>
                <div style="width:10%;"><span class="blueColor">Date</span>  </div>
                <div style="width:5%;"> <span class="blueColor">Delete</span> </div>
                <div style="width:5%;"> <span class="blueColor"><b>Edit</b></span> </div>
            </div>

            <br>
            <hr>
            <br>

            <!-- List Notes -->

            <?php foreach(($notes) as $note){ ?>


                <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">

                    <div style="width:10%;word-wrap:break-word;">
                        <?php echo $note->context ?>
                    </div>

                    <div style="width:10%;word-wrap:break-word;;">
                        <?php echo $note->name ?>
                    </div>

                    <div style="width:25%;word-wrap:break-word;text-align: justify;">
                        <?php echo $note->notetext ?>
                    </div>

                    <div style="width:10%;">
                        <?php echo date('d/m/Y H:i:s', $note->date) ?>
                    </div>

                    <div style="width:5%;">
                    <form action="<?php echo $url ?>" method="post">
                        <input type="hidden" name="noteid" value="<?php echo $note->id ?>">
                        <input type="hidden" name="action" value="delete">
                        <button class="button" type="submit" name="delete">Delete</button>
                    </form>
                    </div>

                    <div style="width:5%;">
                    <form action="<?php echo $url ?>&noteid=<?php echo $note->id ?>" method="post">
                        <input type="hidden" name="noteid" value="<?php echo $note->id ?>">
                        <input type="hidden" name="action" value="edit">
                        <button class="button" type="submit" name="edit">Edit</button>
                    </form>
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

