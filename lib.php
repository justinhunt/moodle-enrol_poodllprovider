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
 * poodllprovider enrolment plugin main library file.
 *
 * @package enrol_poodllprovider
 * @copyright 2020 Justin Hunt <justin@poodll.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use enrol_poodllprovider\data_connector;
use IMSGlobal\LTI\ToolProvider\ToolConsumer;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir.'/filelib.php');
require_once($CFG->libdir.'/gradelib.php');
require_once($CFG->libdir.'/completionlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot . '/course/modlib.php');

/**
 * poodllprovider enrolment plugin class.
 *
 * @package enrol_poodllprovider
 * @copyright 2020 Justin Hunt <justin@poodll.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_poodllprovider_plugin extends enrol_plugin {

    /**
     * Return true if we can add a new instance to this course.
     *
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        return has_capability('moodle/course:enrolconfig', $context) && has_capability('enrol/poodllprovider:config', $context);
    }

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param object $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/poodllprovider:config', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/poodllprovider:config', $context);
    }

    /**
     * Returns true if it's possible to unenrol users.
     *
     * @param stdClass $instance course enrol instance
     * @return bool
     */
    public function allow_unenrol(stdClass $instance) {
        return true;
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Add new instance of enrol plugin.
     *
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, array $fields = null) {
        global $DB;

        $instanceid = parent::add_instance($course, $fields);

        // Add additional data to our table.
        $data = new stdClass();
        $data->enrolid = $instanceid;
        $data->timecreated = time();
        $data->timemodified = $data->timecreated;
        foreach ($fields as $field => $value) {
            $data->$field = $value;
        }

        $DB->insert_record('enrol_pp_tools', $data);

        return $instanceid;
    }

    /**
     * Update instance of enrol plugin.
     *
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        global $DB;

        parent::update_instance($instance, $data);

        // Remove the fields we don't want to override.
        unset($data->id);
        unset($data->timecreated);
        unset($data->timemodified);

        // Convert to an array we can loop over.
        $fields = (array) $data;

        // Update the data in our table.
        $tool = new stdClass();
        $tool->id = $data->toolid;
        $tool->timemodified = time();
        foreach ($fields as $field => $value) {
            $tool->$field = $value;
        }

        return $DB->update_record('enrol_pp_tools', $tool);
    }

    /**
     * Delete plugin specific information.
     *
     * @param stdClass $instance
     * @return void
     */
    public function delete_instance($instance) {
        global $DB;

        // Get the tool associated with this instance.
        $tool = $DB->get_record('enrol_pp_tools', array('enrolid' => $instance->id), 'id', MUST_EXIST);

        // Delete any users associated with this tool.
        $DB->delete_records('enrol_pp_users', array('toolid' => $tool->id));

        // Get tool and consumer mappings.
        $rsmapping = $DB->get_recordset('enrol_pp_tool_consumer_map', array('toolid' => $tool->id));

        // Delete consumers that are linked to this tool and their related data.
        $dataconnector = new data_connector();
        foreach ($rsmapping as $mapping) {
            $consumer = new ToolConsumer(null, $dataconnector);
            $consumer->setRecordId($mapping->consumerid);
            $dataconnector->deleteToolConsumer($consumer);
        }
        $rsmapping->close();

        // Delete mapping records.
        $DB->delete_records('enrol_pp_tool_consumer_map', array('toolid' => $tool->id));

        // Delete the poodllprovider tool record.
        $DB->delete_records('enrol_pp_tools', array('id' => $tool->id));

        // Time for the parent to do it's thang, yeow.
        parent::delete_instance($instance);
    }

    /**
     * Handles un-enrolling a user.
     *
     * @param stdClass $instance
     * @param int $userid
     * @return void
     */
    public function unenrol_user(stdClass $instance, $userid) {
        global $DB;

        // Get the tool associated with this instance. Note - it may not exist if we have deleted
        // the tool. This is fine because we have already cleaned the 'enrol_pp_users' table.
        if ($tool = $DB->get_record('enrol_pp_tools', array('enrolid' => $instance->id), 'id')) {
            // Need to remove the user from the users table.
            $DB->delete_records('enrol_pp_users', array('userid' => $userid, 'toolid' => $tool->id));
        }

        parent::unenrol_user($instance, $userid);
    }

    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        global $DB;

        $nameattribs = array('size' => '20', 'maxlength' => '255');
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'), $nameattribs);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'server');

        $tools = array();
        $tools[$context->id] = get_string('course');
        $modinfo = get_fast_modinfo($instance->courseid);
        $mods = $modinfo->get_cms();
        foreach ($mods as $mod) {
            $tools[$mod->context->id] = format_string($mod->name);
        }

        $mform->addElement('select', 'contextid', get_string('tooltobeprovided', 'enrol_poodllprovider'), $tools);
        $mform->setDefault('contextid', $context->id);

        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_poodllprovider'),
            array('optional' => true, 'defaultunit' => DAYSECS));
        $mform->setDefault('enrolperiod', 0);
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_poodllprovider');

        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_poodllprovider'),
            array('optional' => true));
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_poodllprovider');

        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_poodllprovider'),
            array('optional' => true));
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_poodllprovider');

        $mform->addElement('text', 'maxenrolled', get_string('maxenrolled', 'enrol_poodllprovider'));
        $mform->setDefault('maxenrolled', 0);
        $mform->addHelpButton('maxenrolled', 'maxenrolled', 'enrol_poodllprovider');
        $mform->setType('maxenrolled', PARAM_INT);

        $assignableroles = get_assignable_roles($context);

        $mform->addElement('select', 'roleinstructor', get_string('roleinstructor', 'enrol_poodllprovider'), $assignableroles);
        $mform->setDefault('roleinstructor', '3');
        $mform->addHelpButton('roleinstructor', 'roleinstructor', 'enrol_poodllprovider');

        $mform->addElement('select', 'rolelearner', get_string('rolelearner', 'enrol_poodllprovider'), $assignableroles);
        $mform->setDefault('rolelearner', '5');
        $mform->addHelpButton('rolelearner', 'rolelearner', 'enrol_poodllprovider');

        $mform->addElement('header', 'remotesystem', get_string('remotesystem', 'enrol_poodllprovider'));

        $mform->addElement('text', 'secret', get_string('secret', 'enrol_poodllprovider'), 'maxlength="64" size="25"');
        $mform->setType('secret', PARAM_ALPHANUM);
        $mform->setDefault('secret', random_string(32));
        $mform->addHelpButton('secret', 'secret', 'enrol_poodllprovider');
        $mform->addRule('secret', get_string('required'), 'required');

        $mform->addElement('selectyesno', 'gradesync', get_string('gradesync', 'enrol_poodllprovider'));
        $mform->setDefault('gradesync', 1);
        $mform->addHelpButton('gradesync', 'gradesync', 'enrol_poodllprovider');

        $mform->addElement('selectyesno', 'gradesynccompletion', get_string('requirecompletion', 'enrol_poodllprovider'));
        $mform->setDefault('gradesynccompletion', 0);
        $mform->disabledIf('gradesynccompletion', 'gradesync', 0);

        $mform->addElement('selectyesno', 'membersync', get_string('membersync', 'enrol_poodllprovider'));
        $mform->setDefault('membersync', 1);
        $mform->addHelpButton('membersync', 'membersync', 'enrol_poodllprovider');

        $options = array();
        $options[\enrol_poodllprovider\helper::MEMBER_SYNC_ENROL_AND_UNENROL] = get_string('membersyncmodeenrolandunenrol', 'enrol_poodllprovider');
        $options[\enrol_poodllprovider\helper::MEMBER_SYNC_ENROL_NEW] = get_string('membersyncmodeenrolnew', 'enrol_poodllprovider');
        $options[\enrol_poodllprovider\helper::MEMBER_SYNC_UNENROL_MISSING] = get_string('membersyncmodeunenrolmissing', 'enrol_poodllprovider');
        $mform->addElement('select', 'membersyncmode', get_string('membersyncmode', 'enrol_poodllprovider'), $options);
        $mform->setDefault('membersyncmode', \enrol_poodllprovider\helper::MEMBER_SYNC_ENROL_AND_UNENROL);
        $mform->addHelpButton('membersyncmode', 'membersyncmode', 'enrol_poodllprovider');
        $mform->disabledIf('membersyncmode', 'membersync', 0);

        $mform->addElement('header', 'defaultheader', get_string('userdefaultvalues', 'enrol_poodllprovider'));

        $emaildisplay = get_config('enrol_poodllprovider', 'emaildisplay');
        $choices = array(
            0 => get_string('emaildisplayno'),
            1 => get_string('emaildisplayyes'),
            2 => get_string('emaildisplaycourse')
        );
        $mform->addElement('select', 'maildisplay', get_string('emaildisplay'), $choices);
        $mform->setDefault('maildisplay', $emaildisplay);
        $mform->addHelpButton('maildisplay', 'emaildisplay');

        $city = get_config('enrol_poodllprovider', 'city');
        $mform->addElement('text', 'city', get_string('city'), 'maxlength="100" size="25"');
        $mform->setType('city', PARAM_TEXT);
        $mform->setDefault('city', $city);

        $country = get_config('enrol_poodllprovider', 'country');
        $countries = array('' => get_string('selectacountry') . '...') + get_string_manager()->get_list_of_countries();
        $mform->addElement('select', 'country', get_string('selectacountry'), $countries);
        $mform->setDefault('country', $country);
        $mform->setAdvanced('country');

        $timezone = get_config('enrol_poodllprovider', 'timezone');
        $choices = core_date::get_list_of_timezones(null, true);
        $mform->addElement('select', 'timezone', get_string('timezone'), $choices);
        $mform->setDefault('timezone', $timezone);
        $mform->setAdvanced('timezone');

        $lang = get_config('enrol_poodllprovider', 'lang');
        $mform->addElement('select', 'lang', get_string('preferredlanguage'), get_string_manager()->get_list_of_translations());
        $mform->setDefault('lang', $lang);
        $mform->setAdvanced('lang');

        $institution = get_config('enrol_poodllprovider', 'institution');
        $mform->addElement('text', 'institution', get_string('institution'), 'maxlength="40" size="25"');
        $mform->setType('institution', core_user::get_property_type('institution'));
        $mform->setDefault('institution', $institution);
        $mform->setAdvanced('institution');

        // Check if we are editing an instance.
        if (!empty($instance->id)) {
            // Get the details from the enrol_pp_tools table.
            $ltitool = $DB->get_record('enrol_pp_tools', array('enrolid' => $instance->id), '*', MUST_EXIST);

            $mform->addElement('hidden', 'toolid');
            $mform->setType('toolid', PARAM_INT);
            $mform->setConstant('toolid', $ltitool->id);

            $mform->setDefaults((array) $ltitool);
        }
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        global $COURSE, $DB;

        $errors = array();

        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddateerror', 'enrol_poodllprovider');
        }

        if (!empty($data['requirecompletion'])) {
            $completion = new completion_info($COURSE);
            $moodlecontext = $DB->get_record('context', array('id' => $data['contextid']));
            if ($moodlecontext->contextlevel == CONTEXT_MODULE) {
                $cm = get_coursemodule_from_id(false, $moodlecontext->instanceid, 0, false, MUST_EXIST);
            } else {
                $cm = null;
            }

            if (!$completion->is_enabled($cm)) {
                $errors['requirecompletion'] = get_string('errorcompletionenabled', 'enrol_poodllprovider');
            }
        }

        return $errors;
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        // We want to call the parent because we do not want to add an enrol_pp_tools row
        // as that is done as part of the restore process.
        $instanceid = parent::add_instance($course, (array)$data);
        $step->set_mapping('enrol', $oldid, $instanceid);
    }
}

/**
 * Display the LTI link in the course administration menu.
 *
 * @param settings_navigation $navigation The settings navigation object
 * @param stdClass $course The course
 * @param stdclass $context Course context
 */
function enrol_poodllprovider_extend_navigation_course($navigation, $course, $context) {
    // Check that the LTI plugin is enabled.
    if (enrol_is_enabled('poodllprovider')) {
        // Check that they can add an instance.
        $poodllproviderplugin = enrol_get_plugin('poodllprovider');
        if ($poodllproviderplugin->can_add_instance($course->id)) {
            $url = new moodle_url('/enrol/poodllprovider/index.php', array('courseid' => $course->id));
            $settingsnode = navigation_node::create(get_string('sharedexternaltools', 'enrol_poodllprovider'), $url,
                navigation_node::TYPE_SETTING, null, null, new pix_icon('i/settings', ''));

            $navigation->add_node($settingsnode);
        }
    }
}

/**
 * Serve the new group form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function enrol_poodllprovider_output_fragment_new_group_form($args) {
    global $CFG;

    require_once($CFG->dirroot . '/group/group_form.php');
    $args = (object) $args;
    $context = $args->context;

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    list($ignored, $course) = get_context_info_array($context->id);
    $group = new stdClass();
    $group->courseid = $course->id;

    //require_capability('moodle/course:managegroups', $context);
    $editoroptions = [
        'maxfiles' => EDITOR_UNLIMITED_FILES,
        'maxbytes' => $course->maxbytes,
        'trust' => false,
        'context' => $context,
        'noclean' => true,
        'subdirs' => false
    ];
    $group = file_prepare_standard_editor($group, 'description', $editoroptions, $context, 'group', 'description', null);

    $mform = new group_form(null, array('editoroptions' => $editoroptions), 'post', '', null, true, $formdata);
    // Used to set the courseid.
    $mform->set_data($group);

    if (!empty($args->jsonformdata)) {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();

    return $o;
}
/**
 * Serve the new readaloud form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function enrol_poodllprovider_output_fragment_readaloud_form($args) {
    return mod_form_output($args, 'readaloud');
}

/**
 * Serve the new wordcards form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function enrol_poodllprovider_output_fragment_wordcards_form($args) {
    return mod_form_output($args, 'wordcards');
}
/**
 * Serve the new group form as a fragment.
 *
 * @param array $args List of named arguments for the fragment loader.
 * @return string
 */
function enrol_poodllprovider_output_fragment_poodlltime_form($args) {
    return mod_form_output($args, 'poodlltime');
}

/**
 * Render module form
 *
 * @param $args
 * @param $modname
 * @return string
 * @throws moodle_exception
 */
function mod_form_output($args, $modname) {
    global $CFG;

    $modmoodleform = "$CFG->dirroot/mod/".$modname."/mod_form.php";

    if (file_exists($modmoodleform)) {
        require_once($modmoodleform);
    } else {
        print_error('noformdesc');
    }

    $args = (object) $args;
    $context = $args->context;
    $cmid = $args->cmid;

    $formdata = [];
    if (!empty($args->jsonformdata)) {
        $serialiseddata = json_decode($args->jsonformdata);
        parse_str($serialiseddata, $formdata);
    }

    list($ignored, $course) = get_context_info_array($context->id);

    $section = 0;
    $sectionreturn = null;

    if (!empty($cmid)) {
        // Check the course module exists.
        $cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);

        list($cm, $contextcourse, $module, $data, $cw) = get_moduleinfo_data($cm, $course);
        $data->return = 0;
        $data->sr = $sectionreturn;
        $data->update = $cmid;
    } else {
        list($module, $contextcourse, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $modname, $section);
        $data->return = 0;
        $data->sr = $sectionreturn;
        $data->add = $modname;
    }
    $mformclassname = 'mod_'.$modname.'_mod_form';
    $mform = new $mformclassname($data, $cw->section, $cm, $course);
    $mform->set_data($data);

    if (!empty($args->jsonformdata) && $args->jsonformdata != '{}') {
        // If we were passed non-empty form data we want the mform to call validation functions and show errors.
        $mform->is_validated();
    }

    $o = '';
    ob_start();
    $mform->display();
    $o .= ob_get_contents();
    ob_end_clean();

    return $o;
}
