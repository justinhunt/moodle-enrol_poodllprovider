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
 * External API
 *
 * @package     enrol_poodllprovider
 * @copyright   2019 Michael Gardener <mgardener@cissq.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_poodllprovider;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->libdir . '/externallib.php');
require_once($CFG->dirroot . '/enrol/poodllprovider/lib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_format_value;
use external_single_structure;
use external_multiple_structure;
use external_description;
use context_course;
use context_module;
use stdClass;
use enrol_poodllprovider_plugin;

/**
 * External functions
 *
 * @package     enrol_poodllprovider
 * @copyright   2019 Michael Gardener <mgardener@cissq.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends \external_api {

    /**
     * Describes the parameters for manage_course_module webservice.
     * @return external_function_parameters
     */
    public static function manage_course_module_parameters() {
        return new external_function_parameters(
            array(
                'contextid' => new external_value(PARAM_INT, 'The context id for the course'),
                'itemnumber' => new external_value(PARAM_INT, 'Tool ID'),
                'cmid' => new external_value(PARAM_INT, 'Course Module ID'),
                'jsonformdata' => new external_value(PARAM_RAW, 'The data from the create group form, encoded as a json array')
            )
        );
    }

    /**
     * Submit the create group form.
     *
     * @param int $contextid The context id for the course.
     * @param int $itemnumber Tool ID.
     * @param int $cmid Course module ID
     * @param string $jsonformdata The data from the form, encoded as a json array.
     * @return int new group id.
     */
    public static function manage_course_module($contextid, $itemnumber, $cmid, $jsonformdata) {
        global $CFG, $USER, $DB;

        require_once($CFG->dirroot . '/group/lib.php');
        require_once($CFG->dirroot . '/group/group_form.php');

        // We always must pass webservice params through validate_parameters.
        $params = self::validate_parameters(self::manage_course_module_parameters(),
            [
                'contextid' => $contextid,
                'itemnumber' => $itemnumber,
                'cmid' => $cmid,
                'jsonformdata' => $jsonformdata
            ]);

        $context = \context::instance_by_id($params['contextid'], MUST_EXIST);

        // We always must call validate_context in a webservice.
        self::validate_context($context);
        require_capability('moodle/course:manageactivities', $context);

        list($ignored, $course) = get_context_info_array($context->id);
        $serialiseddata = json_decode($params['jsonformdata']);

        $data = array();
        parse_str($serialiseddata, $data);

        $cmid = self::submit_mod_edit_form($data);

        if (!$params['cmid']) {
            $contextmodule = \context_module::instance($cmid);

            // Add enrol instance.
            $toolinstance = $DB->get_record('enrol_pp_tools', ['id' => $params['itemnumber']]);

            $plugin = enrol_get_plugin('poodllprovider');
            $instance = (object)$plugin->get_instance_defaults();
            $instance->id = null;
            $instance->courseid = $course->id;
            $instance->status = ENROL_INSTANCE_ENABLED;
            $instance->name = $data['name'];
            $instance->contextid = $contextmodule->id;

            unset($toolinstance->id);
            unset($toolinstance->enrolid);

            foreach ($toolinstance as $index => $item) {
                if (!isset($instance->{$index})) {
                    $instance->{$index} = $item;
                }
            }

            $fields = (array)$instance;
            $enrolid = $plugin->add_instance($course, $fields);

            $tool = $DB->get_record('enrol_pp_tools', ['enrolid' => $enrolid]);

            return \enrol_poodllprovider\helper::render_lti_tool_item($tool->id);

        }
        return '';
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function manage_course_module_returns() {
        return new external_value(PARAM_RAW);
    }

    /**
     * Describes the parameters for delete_modules.
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function delete_modules_parameters() {
        return new external_function_parameters (
            array(
                'cmids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'course module ID', VALUE_REQUIRED, '', NULL_NOT_ALLOWED),
                    'Array of course module IDs'
                ),
            )
        );
    }

    /**
     * Deletes a list of provided module instances.
     *
     * @param array $cmids the course module ids
     * @since Moodle 2.5
     */
    public static function delete_modules($cmids) {
        global $CFG, $DB;

        // Require course file containing the course delete module function.
        require_once($CFG->dirroot . "/course/lib.php");

        // Clean the parameters.
        $params = self::validate_parameters(self::delete_modules_parameters(), array('cmids' => $cmids));

        // Keep track of the course ids we have performed a capability check on to avoid repeating.
        $arrcourseschecked = array();

        foreach ($params['cmids'] as $cmid) {
            // Get the course module.
            $cm = $DB->get_record('course_modules', array('id' => $cmid), '*', MUST_EXIST);

            // Check if we have not yet confirmed they have permission in this course.
            if (!in_array($cm->course, $arrcourseschecked)) {
                // Ensure the current user has required permission in this course.
                $context = context_course::instance($cm->course);
                self::validate_context($context);
                // Add to the array.
                $arrcourseschecked[] = $cm->course;
            }

            // Ensure they can delete this module.
            $modcontext = context_module::instance($cm->id);
            require_capability('moodle/course:manageactivities', $modcontext);

            // Delete the module.
            course_delete_module($cm->id);

            // Delete enrol.
            $toolinstance = $DB->get_record('enrol_pp_tools', ['contextid' => $modcontext->id]);
            $instance = $DB->get_record('enrol', ['id' => $toolinstance->enrolid]);

            $plugin = enrol_get_plugin('poodllprovider');
            $plugin->delete_instance($instance);
        }
    }

    /**
     * Describes the delete_modules return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function delete_modules_returns() {
        return null;
    }

    /**
     * @param array $formdata
     * @return mixed
     * @throws \coding_exception
     * @throws \dml_exception
     * @throws \moodle_exception
     * @throws \require_login_exception
     */
    public static function submit_mod_edit_form(array $formdata) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->libdir.'/filelib.php');
        require_once($CFG->libdir.'/gradelib.php');
        require_once($CFG->libdir.'/completionlib.php');
        require_once($CFG->libdir.'/plagiarismlib.php');
        require_once($CFG->dirroot . '/course/modlib.php');

        $add    = $formdata['add'] ?? '';     // Module name.
        $update = $formdata['update'] ?? 0;
        $return = $formdata['return'] ?? 0;
        $type   = $formdata['type'] ?? '';
        $sectionreturn = $formdata['sr'] ?? null;

        if (!empty($add)) {
            $section = $formdata['section'] ?? 0;
            $course  = $formdata['course'] ?? 0;
            $course = $DB->get_record('course', array('id'=>$course), '*', MUST_EXIST);

            list($module, $context, $cw, $cm, $data) = prepare_new_moduleinfo_data($course, $add, $section);
            $data->return = 0;
            $data->sr = $sectionreturn;
            $data->add = $add;
            if (!empty($type)) { //TODO: hopefully will be removed in 2.0
                $data->type = $type;
            }
        } else if (!empty($update)) {
            // Check the course module exists.
            $cm = get_coursemodule_from_id('', $update, 0, false, MUST_EXIST);

            // Check the course exists.
            $course = $DB->get_record('course', array('id'=>$cm->course), '*', MUST_EXIST);

            // require_login
            require_login($course, false, $cm); // needed to setup proper $COURSE

            list($cm, $context, $module, $data, $cw) = get_moduleinfo_data($cm, $course);
            $data->return = $return;
            $data->sr = $sectionreturn;
            $data->update = $update;
        } else {
            print_error('invalidaction');
        }

        $poodllforms =['readaloud','minilesson','wordcards'];
        //others are listed in mod_forms, but pretty much everything that has meaning in lti
        $mformclassname = 'mod_'.$module->name.'_mod_form';
        $mform=false;

        //build a form. Poodll form constructors work with ajax data
        //regular plugins, require us to subclass them with a new constructor (mod_forms.php)..
        //.. then change the incoming form name in the data, so the form get_data works properly
error_log('modname:');
error_log($module->name);
        if(in_array($module->name,$poodllforms)) {
error_log('it was in poodll forms:');
            $modmoodleform = "$CFG->dirroot/mod/$module->name/mod_form.php";
            if (file_exists($modmoodleform)) {
                require_once($modmoodleform);
                $mform = new $mformclassname($data, $cw->section, $cm, $course, $formdata);
            } else {
                print_error('noformdesc');
            }
        }else {
error_log('it was NOT in poodll forms:');
            require_once("$CFG->dirroot/enrol/poodllprovider/mod_forms.php");
error_log('we have everything that we require:');
            $ajaxformclassname = 'ajax_' . $mformclassname;
            unset($formdata['_qf__' . $mformclassname]);
            $formdata['_qf__' . $ajaxformclassname]=1;
error_log('ajaxname:');
error_log($ajaxformclassname);
error_log('formdata:');
error_log($formdata);
            $mform = new $ajaxformclassname($data, $cw->section, $cm, $course, $formdata);
        }

        if(!$mform){
error_log('ouch');
            print_error('invaliddata');
        }else {

            if ($fromform = $mform->get_data()) {
                if (!empty($fromform->update)) {
                    list($cm, $fromform) = update_moduleinfo($cm, $fromform, $course, $mform);
                } else if (!empty($fromform->add)) {
                    $fromform = add_moduleinfo($fromform, $course, $mform);
                } else {
error_log('triple ouch');
                    print_error('invaliddata');
                }
            } else {
error_log('doubleouch');
                print_error('invaliddata');
            }
            return $fromform->coursemodule;
        }
    }
}