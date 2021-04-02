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
require_once($CFG->dirroot . '/mod/assign/lib.php');



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
    $data->instance = $data->id;
    $data->coursemodule = $cm->id;

    //intro editor
    $data->intro = file_save_draft_area_files($data->introeditor['itemid'], $modulecontext->id,
            'mod_assign', 'intro', 0,
            array('subdirs'=>true), $data->introeditor['text']);
    //$data->introformat = $moduleinfo->introeditor['format'];
    unset($data->introeditor);

    foreach ($moduleinstance as $key => $value) {
        if(!isset($data->{$key})){
            $data->{$key}=$value;
        }
    }

    //now update the db once we have saved files and stuff
    if (assign_update_instance($data,null)) {
        redirect($redirecturl);
        exit;
    }
}

//if we got here we are loading up data
$ctx = context_module::instance($cm->id);
$assignment = new assign($ctx, null, null);
$assignment->set_course($course);
$filerelatedvalues =[];
$moduleinstance->n =$moduleinstance->id;
$formdata = (array)$moduleinstance;


//intro editor
$draftitemid_editor = file_get_submitted_draft_itemid('introeditor');
$currentintro = file_prepare_draft_area($draftitemid_editor, $ctx->id, 'mod_assign',
        'intro', 0, array('subdirs'=>true), $moduleinstance->intro);
$formdata['introeditor'] = array('text'=>$currentintro, 'format'=>$moduleinstance->introformat, 'itemid'=>$draftitemid_editor);

//attachments
$draftitemid_attachments = file_get_submitted_draft_itemid('introattachments');
file_prepare_draft_area($draftitemid_attachments, $ctx->id, 'mod_assign', ASSIGN_INTROATTACHMENT_FILEAREA,
        0, array('subdirs' => 0));
$formdata['introattachments'] = $draftitemid_attachments;

//plugins
$filerelatedvalues['introattachments'] = $draftitemid_attachments;
$assignment->plugin_data_preprocessing($filerelatedvalues);

$mform->set_data($formdata);

echo $renderer->setup_header($moduleinstance, $modulecontext, $id);
$mform->display();
echo $renderer->footer();
