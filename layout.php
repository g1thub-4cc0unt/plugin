<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        img.imageclass {
            height: 100%; width: 100%;object-fit: contain;
        }
        div.menuButtons{
            border-radius: 15px;
            height: auto; width: 90%;
            padding: 3%;
        }
        .menuButtons:hover{
            background-color: #007FFF;
        }
        .menuButtons:active {
            background-color: #007FFF;
        }
    </style>
</head>

<body style="background-color:#EEEEEE">

<!-- Heading - Search Bar -->
<div style="width:100%;height:6%; top:0.5%; position: absolute;background-image: linear-gradient(to right, #007FFF, #B3D4FF);
        display: flex;
        flex-direction: row;
        justify-content: center">

    <div style="align-self:center">
        <h2 style="color:white;font-family:arial;">Course: <?php echo "Heading" ?></h2>
    </div>

    <!-- Course Search Bar -->
    <div style="position: absolute;  top: 25%;right:5%; ">
        <form>
            <input type="search" name="course"
                   placeholder="Search Course">
            <button>
                &#128269;
            </button>
        </form>
    </div>

</div>


<?php
require_once(__DIR__. "/../../config.php");

$url = new moodle_url($CFG->wwwroot."/local/analytics/");

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




?>
<?php echo $url."index.php?course=".$course_name ?>

<!-- Menu Buttons -->
<div style=" width:3.5%; height: 100%; background-color:#3B3C3B;
        display: flex;
        flex-direction: column;
        justify-content: center;
       ">

    <div style="
        width:100%;
        height: 50%;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5%;">


        <div class="menuButtons" >
            <a href="<?php echo $url."index.php?course=".$course_name ?>">
                <img src="./icons/Home.png" class="imageclass">
            </a>
        </div>

        <div class="menuButtons" >
            <a href="<?php echo $url."grades_time.php?course=".$course_name ?>">
                <img src="./icons/Analytics.png" class="imageclass">
            </a>
        </div>

        <div class="menuButtons">
            <a href="<?php echo $url."students_overview.php?course=".$course_name ?>">
                <img src="./icons/StudentOverview.png"class="imageclass">
            </a>
        </div>

        <div class="menuButtons">
            <a href="<?php echo $url."notes.php?course=".$course_name ?>">
                <img src="./icons/Notes.png" class="imageclass">
            </a>
        </div>

        <div class="menuButtons">
            <a href="<?php echo $url."faq.php?course=".$course_name ?>">
             <img src="./icons/FAQ.png" class="imageclass">
            </a>
        </div>
    </div>
</div>

