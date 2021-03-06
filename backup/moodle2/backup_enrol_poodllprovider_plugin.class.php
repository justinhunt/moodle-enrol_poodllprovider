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
 * Defines the backup_enrol_poodllprovider_plugin class.
 *
 * @package   enrol_poodllprovider
 * @copyright 2020 Justin Hunt <justin@poodll.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define all the backup steps.
 *
 * @package   enrol_poodllprovider
 * @copyright 2020 Justin Hunt <justin@poodll.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_enrol_poodllprovider_plugin extends backup_enrol_plugin {

    /**
     * Defines the other poodllprovider enrolment structures to append.
     *
     * @return backup_plugin_element
     */
    public function define_enrol_plugin_structure() {
        // Get the parent we will be adding these elements to.
        $plugin = $this->get_plugin_element();

        // Define our elements.
        $pptool = new backup_nested_element('pptool', array('id'), array(
            'enrolid', 'contextid', 'institution', 'lang', 'timezone', 'maxenrolled', 'maildisplay', 'city',
            'country', 'gradesync', 'gradesynccompletion', 'membersync', 'membersyncmode',  'roleinstructor',
            'rolelearner', 'secret','modtypes', 'timecreated', 'timemodified'));

        $ppusers = new backup_nested_element('ppusers');

        $ppuser = new backup_nested_element('ppuser', array('id'), array(
            'userid', 'toolid', 'serviceurl', 'sourceid', 'consumerkey', 'consumersecret', 'membershipurl',
            'membershipsid'));

        // Build elements hierarchy.
        $plugin->add_child($pptool);
        $pptool->add_child($ppusers);
        $ppusers->add_child($ppuser);

        // Set sources to populate the data.
        $pptool->set_source_table('enrol_pp_tools',
            array('enrolid' => backup::VAR_PARENTID));

        // Users are only added only if users included.
        if ($this->task->get_setting_value('users')) {
            $ppuser->set_source_table('enrol_pp_users', array('toolid' => backup::VAR_PARENTID));
        }
    }
}
