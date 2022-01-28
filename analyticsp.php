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

global $DB;

$PAGE->set_url(new moodle_url("/local/analytics/analyticsp.php"));
$PAGE->set_context(\context_system::instance());
$PAGE->set_title("Make notes");

$notes = $DB->get_records("local_analytics_notes");


foreach($notes as $key => $note){
    $notes[$key]->date = date('d/m/Y H:i:s', $note->date);
}


echo $OUTPUT->header();
$templatecontext = (object)[
    "notes" => array_values($notes),
    "editurl" => new moodle_url("/local/analytics/edit.php"),
];


echo $OUTPUT->render_from_template("local_analytics/analyticsp", $templatecontext);

echo $OUTPUT->footer();