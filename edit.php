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
require_once($CFG->dirroot."/local/analytics/classes/form/edit.php");

global $DB;

$PAGE->set_url(new moodle_url("/local/analytics/edit.php"));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title("Create and Edit");
  
  
$mform = new note_edit();


//Form processing and displaying is done here
if ($mform->is_cancelled()) {
    //Handle form cancel operation, if cancel button is present on form
    redirect($CFG->wwwroot."/local/analytics/analyticsp.php");
} else if ($fromform = $mform->get_data()) {
    //Insert Data into database
    $notetext = $fromform->notetext;

    $record = new stdClass();
    $record->notetext = $fromform->notetext;
    $record->date = time();
    $record->context = "Context";

    $DB->insert_record("local_analytics_notes", $record);

    redirect($CFG->wwwroot."/local/analytics/analyticsp.php", "You created a note: ".$fromform->notetext);
}

echo $OUTPUT->header();
echo "<h1>Logging</h1>";
echo"<p></p>";
$mform->display();

echo $OUTPUT->footer();