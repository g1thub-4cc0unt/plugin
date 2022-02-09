<?php

/*$date = date("m/d/Y", time());
$dateEpoch = date_create($date)->format('U');*/




$courseviewurl = new moodle_url('/course/view.php', ['id' => $courseId]);
echo '<a href="' . $courseviewurl . '">Back to the course</a>';

//Read Course ID, Startdate, Enddate
$course_id = required_param("course", PARAM_INT);
$sql = "SELECT c.* FROM {course} c WHERE c.id = (?)";
$records = $DB->get_records_sql($sql, [$course_id]);

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

