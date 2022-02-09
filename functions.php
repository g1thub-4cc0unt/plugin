<?php
function getCourseInfo($id){
    global $DB;
    //Read Course ID, Startdate, Enddate
    $course_id = required_param("courseid", PARAM_INT);
    $sql = "SELECT c.* FROM {course} c WHERE c.id = (?)";
    $records = $DB->get_records_sql($sql, [$course_id]);


    $course = new stdClass();
    if (count($records) > 0){
        foreach ($records as $c) {
            $course -> id = $c->id;
            $course -> name = $c->fullname;
            $course -> startDateEpoch = $c->startdate;
            $course -> endDateEpoch = $c->enddate;
            $course -> startDate = date('d/m/Y', $c->startdate);
        }
    }
    return $course;
}

//https://stats.stackexchange.com/a/154211
function normalize($value, $min, $max) {
    $normalized = ($value - $min) / ($max - $min);
    return $normalized;
}