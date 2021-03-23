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
 * Sets up the tabs at the top of the module view page　for teachers.
 *
 * This file was adapted from the mod/lesson/tabs.php
 *
 * @package poodll_poodllprovider
 * @copyright  2021 Justin Hunt  {@link http://poodll.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or late
 */

defined('MOODLE_INTERNAL') || die();


/// This file to be included so we can assume config.php has already been included.
global $DB;

if (!isset($currenttab)) {
    $currenttab = '';
}
$component = 'mod_assign';
if (!isset($course)) {
    $course = $DB->get_record('course', array('id' => $moduleinstance->course));
}

$tabs = $row = $inactive = $activated = array();

$row[] = new tabobject('view', "$CFG->wwwroot/mod/assign/view.php?id=$header->coursemoduleid", get_string('view', 'enrol_poodllprovider'),
        get_string('view', 'enrol_poodllprovider'));

if (has_capability('mod/assign:addinstance', $header->context)) {
    $row[] = new tabobject('assign_setup', "$CFG->wwwroot/enrol/poodllprovider/assign/setup.php?id=$header->coursemoduleid",
            get_string('setup', 'enrol_poodllprovider'), get_string('setup', 'enrol_poodllprovider'));
}

if (has_capability('mod/assign:grade', $header->context)) {
    $row[] = new tabobject('assign_grading', "$CFG->wwwroot/enrol/poodllprovider/assign/view.php?id=$header->coursemoduleid&action=grading",
            get_string('grading', 'enrol_poodllprovider'), get_string('setup', 'enrol_poodllprovider'));
}
$tabs[] = $row;

print_tabs($tabs, $currenttab, $inactive, $activated);
