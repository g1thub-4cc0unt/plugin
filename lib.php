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

function local_analytics_extend_navigation(global_navigation $navigation){
    global $OUTPUT;
    $url = $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
    if ((isset($_GET['id'])) & (strpos($url, 'course/view.php?id') !== false)){


        //If no right to see plugin => return
        if (!(has_capability('local/analytics:view', CONTEXT_COURSE::instance($_GET['id'])))) {
            return;
        }

        $icon = new pix_icon( "scales", "scales", "local_analytics");
        $main_node = $navigation->add(get_string("Analytics", "local_analytics"), "/local/analytics/index.php/?courseid=".$_GET['id']."&r");
        $main_node -> nodetype = 1;
        $main_node -> icon = $icon;
        $main_node -> forceopen = true;
        $main_node -> showinflatnavigation = true;
    }
}