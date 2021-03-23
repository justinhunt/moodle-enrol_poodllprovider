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
 * Setup Tab for mod_assign in enrol_provider
 *
 * @package    enrol_poodlprovider
 * @copyright  2021 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/config.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/mod/assign/mod_form.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');



global $DB;

$modname='assign';
$component='mod_assign';

// Course module ID.
$id = optional_param('id',0, PARAM_INT); // course_module ID, or
$cmid = optional_param('cmid',0, PARAM_INT); // course_module ID, or

if($cmid) {
    list ($course, $cm) = get_course_and_cm_from_cmid($cmid, 'assign');
}else{
    list ($course, $cm) = get_course_and_cm_from_cmid($id, 'assign');
}
$moduleinstance = $DB->get_record($modname, array('id' => $cm->instance), '*', MUST_EXIST);


$modulecontext = context_module::instance($cm->id);
require_capability('mod/assign:addinstance', $modulecontext);

// Set page login data.
$PAGE->set_url($CFG->dirroot  . '/enrol/poodllprovider/assign/setup.php',array('id'=>$cm->id));
require_login($course, true, $cm);


// Set page meta data.
$PAGE->set_title(format_string($moduleinstance->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($modulecontext);
$PAGE->set_pagelayout('course');




// Render template and display page.
$renderer = $PAGE->get_renderer($component);

$mform = new \enrol_poodllprovider\assign_setupform(null,['context'=>$modulecontext, 'cm'=>$cm, 'course'=>$course]);

$redirecturl = new moodle_url('/mod/assign/view.php', array('id'=>$cm->id));
//if the cancel button was pressed, we are out of here
if ($mform->is_cancelled()) {
    redirect($redirecturl);
    exit;
}else if ($data = $mform->get_data()) {

    $data->timemodified = time();
    //$data->id = $data->n;
    $data->coursemodule = $cm->id;
    //$data = assign_process_files($data);

    //now update the db once we have saved files and stuff
    if ($DB->update_record($modname, $data)) {
        redirect($redirecturl);
        exit;
    }
}

//if we got here we is loading up dat form
//$moduleinstance = utils::prepare_file_and_json_stuff($moduleinstance,$modulecontext);

$moduleinstance->n =$moduleinstance->id;
$mform->set_data((array)$moduleinstance);

echo $renderer->setup_header($moduleinstance, $modulecontext, $id);
$mform->display();
echo $renderer->footer();
