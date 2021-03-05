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
//

/**
 * This file keeps track of upgrades to the lti enrolment plugin
 *
 * @package enrol_poodllprovider
 * @copyright  2020 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

 defined('MOODLE_INTERNAL') || die;

/**
 * xmldb_lti_upgrade is the function that upgrades
 * the lti module database when is needed
 *
 * This function is automaticly called when version number in
 * version.php changes.
 *
 * @param int $oldversion New old version number.
 *
 * @return boolean
 */
function xmldb_enrol_poodllprovider_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2021030100) {

        // Define field instanceid to be added to enrol_paypal.
        // For some reason, some Moodle instances that are upgraded from old versions do not have this field.
        $table = new xmldb_table('enrol_poodllprovider');
        $field = new xmldb_field('modtypes', XMLDB_TYPE_TEXT, null, null, null, null);

        // Conditionally launch add field instanceid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Paypal savepoint reached.
        upgrade_plugin_savepoint(true, 2021030100, 'enrol', 'poodllprovider');
    }

    return true;
}
