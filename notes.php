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
        <title>Notes</title>
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
                background-color: #0074D9;
                border: none;
                color: white;
                font-family: Arial;
                padding: 4% 8%;
                cursor: pointer;
                text-align: center;
                font-size: 16px;
                border-radius: 15px;
            }
            .button:hover {
                transition: all .2s ease-in-out;
                background-color: #001F3F;
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


//Needed in Notes HTML
$analytics_url = new moodle_url($CFG->wwwroot."/local/analytics/notes.php");
$url = $analytics_url . "?courseid=". $course_id;

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
if (isset($_POST["action"]) AND ($_POST['noteid'] != 0) AND $_POST["action"] == "create" ){
    $record = new stdClass();
    $record->id = $_POST["noteid"];
    $record->notetext = $_POST["notetext"];
    $record->courseid = $courseId;
    $record->date = time();
    $record->name = $_POST["name"];
    $record->context = $_POST["context"];

    $DB->update_record("local_analytics_notes", $record, $bulk=false);

    redirect($url);
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

    redirect($url);

}

//Delete Note
elseif (isset($_POST["action"]) AND $_POST["action"] == "delete" ){
    $DB->delete_records_select("local_analytics_notes", 'id= ?',[$_POST["noteid"]]);

    redirect($url);
}

//Notes Order by
if (isset($_GET['sort']) AND (isset($_GET['asc']))) {
    //Context Ascending
    if (($_GET['sort'] == "context") AND ($_GET['asc']) == "true") {
        usort($notes, function($a, $b) {return strcmp($a->context, $b->context);});
    }
    //Context Descending
    elseif(($_GET['sort'] == "context") AND ($_GET['asc']) == "false"){
        usort($notes, function ($a, $b) {
            return strcmp($a->context, $b->context);});
        $notes = array_reverse($notes);
    }
    //Name Ascending
    elseif(($_GET['sort'] == "name") AND ($_GET['asc']) == "true"){
        usort($notes, function($a, $b) {
            return strcmp($a->name, $b->name);});
    }
    //Name Descending
    elseif(($_GET['sort'] == "name") AND ($_GET['asc']) == "false"){
        usort($notes, function($a, $b) {
            return strcmp($a->name, $b->name);});
    $notes = array_reverse($notes);
    }
    //Date Ascending
    elseif(($_GET['sort'] == "date") AND ($_GET['asc']) == "true"){
        usort($notes, function ($a, $b) {
            return ($a->date >= $b->date);});
    }
    //Date Descending
    elseif(($_GET['sort'] == "date") AND ($_GET['asc']) == "false"){
        usort($notes, function ($a, $b) {
            return ($a->date <= $b->date);});
    }
}


//Get Students with most notes
$sql ="SELECT  name, count(*) AS amount
           FROM {local_analytics_notes}
           WHERE courseid = ? and name <> ''
           GROUP BY name
           ORDER BY count(*) DESC
           LIMIT 5";
$studentsP = $DB->get_records_sql($sql,[$courseId]);

$colors = [ '#E3655B', '#CFD186', '#5B8C5A', '#596157', '#001F3F', '#001F3F', '#4D7EA8', '#828489', '#9E90A2', '#B6C2D9'];

$counter = 0;
foreach ($studentsP as $key => $student){
    $student -> color = $colors[$counter];
    $studentsP[$key] = $student;
    $counter=$counter+1;
}
//if empty graph
if (count($studentsP) == 0){
    $student = new stdClass();
    $student -> name = "";
    $student -> amount = 0;
    $student -> color = "red";
    $studentsP[] = $student;
}

$counter = 5;
//Get most popular Tags/Context
$sql ="SELECT  context, count(*) AS amount
           FROM {local_analytics_notes}
           WHERE courseid = ? and context <> ''
           GROUP BY context
           ORDER BY count(*) DESC
           LIMIT 5";
$tags = $DB->get_records_sql($sql,[$courseId]);

foreach ($tags as $key => $tag){
    $tag -> color = $colors[$counter];
    $tags[$key] = $tag;
    $counter=$counter+1;
}
//if empty graph
if (count($tags) == 0){
    $tag = new stdClass();
    $tag -> context = "";
    $tag -> amount = 0;
    $tag -> color = "blue";
    $tags[] = $tag;
}


?>
    <!-- Popular Tags -->
    <script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
    <script type="text/javascript">
        google.charts.load('current', {'packages':['corechart']});
        google.charts.setOnLoadCallback(drawChart);
        google.charts.setOnLoadCallback(drawChartI);

        function drawChart() {
            var data = google.visualization.arrayToDataTable([
                ['Tag', 'Notes', {role: 'style'}],
                <?php
                foreach($tags as $resource){
                    echo "['".$resource->context."',".round($resource->amount,2).",'".$resource->color."'],";
                }
                ?>
            ]);

            var options = {
                title: 'Popular Tags',
                legend: { position: 'none' },
                chart: { title: 'Popular Tags',
                    subtitle: ' ' },
                bars: 'horizontal', // Required for Material Bar Charts.
                hAxis: {
                    viewWindow:{
                    }
                }
            };

            var chart = new google.visualization.BarChart(document.getElementById('chart_popular_tags'));
            chart.draw(data, options);
        };

    <!-- Students with most Notes -->
        function drawChartI() {
            var data = google.visualization.arrayToDataTable([
                ['Name', 'Notes', {role: 'style'}],
                <?php  //.
                foreach($studentsP as $key => $student){
                    echo "['".$student->name."',".round($student->amount,2).",'".$student->color."'],";
                }
                ?>
            ]);

            var options = {
                title: 'Notes Per Student',
                legend: { position: 'none' },
                chart: { title: 'Notes Per Student'},
                bars: 'horizontal', // Required for Material Bar Charts.
                hAxis: {
                    viewWindow:{

                    }
                }
            };

            var chart = new google.visualization.BarChart(document.getElementById('chart_popular_students'));
            chart.draw(data, options);
        };
    </script>

    <!-- Heading View -->
    <div style="position: fixed;top: 70px; left:125px; font-family: Arial; z-index: 4">
        <h2>Notes</h2>
    </div>

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
                    <input type="text" placeholder="Search By Tag" name="scontext">
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
                    <div><input type="text" list="predefinedtags" name="context" placeholder="Note Tag" class="inputfield" style="width:180px"
                           value="<?php if (isset($_GET['noteid']))  {echo $noteToEdit->context;} ?>" required>
                        <datalist id="predefinedtags">
                            <option value="Activity">
                            <option value="Forum">
                            <option value="General">
                            <option value="Grade">
                            <option value="Material Usage">
                            <option value="Performance">

                        </datalist>
                    </div>
                    <div>
                    <input type="text" name="name" placeholder="Student Name" class="inputfield" style="width:180px"
                           value="<?php if (isset($_GET['noteid']))  {echo $noteToEdit->name;} ?>">
                    </div>
                    <div>
                    <textarea name="notetext" cols="30" rows="4" class="inputfield" style="width:300px; height: 62px"
                              placeholder="Note Text" required><?php if (isset($_GET['noteid']))  {echo $noteToEdit->notetext;} ?></textarea>
                    </div>
                    <div>
                    <button class="buttonSave" type="submit" name="save">
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

        <?php
        $urlSort = new moodle_url($CFG->wwwroot."/local/analytics/notes.php?courseid=".$course_id);
        ?>
        <div style="overflow-y: scroll; width:60%; height:40%; top:40%; right: 20% ;position: absolute ">

            <div style="font-family:Arial; display: flex;  justify-content:space-evenly;">
                <div style="width:10%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Tag</span>
                    <!-- Order by Context -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=context&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=context&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
                <div style="width:10%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Student <br> Name</span>
                    <!-- Order by Student Name -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=name&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=name&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
                <div style="width:25%;word-wrap:break-word;"class="blueColor"> <span class="blueColor">Text</span> </div>
                <div style="width:10%;word-wrap:break-word;display: flex;justify-content: normal;column-gap: 20px;" class="blueColor"> <span class="blueColor">Date</span>
                    <!-- Order by Date -->
                    <div>

                        <a href="<?php echo $urlSort."&sort=date&asc=true"?>">
                            <div>
                                <img src="./icons/arrow_up.png" width="20px" height="20px">
                            </div>
                        </a>

                        <a href="<?php echo $urlSort."&sort=date&asc=false"?>">
                            <div>
                                <img src="./icons/arrow_down.png" width="20px" height="20px">
                            </div>
                        </a>
                    </div>
                </div>
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
                        <button class="buttonDelete" type="submit" name="delete">Delete</button>
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
        <div style=" top:90%; position: absolute; right:15%;display: flex; width:70%; justify-content:space-evenly;">


            <div style=" width:40%;height:400px;  ">
                <!-- Resources Viewed -->
                <div id="chart_popular_tags" style="height:300px;width: 500px"></div>
            </div>
            <div style=" width:40%;height:400px  ">
                <!-- Resources Viewed -->
                <div id="chart_popular_students" style="height:300px;width: 500px"></div>
            </div>
        </div>
    </div>
</body>
<?php

