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
 * External functions and service definitions.
 * @package     enrol_poodllprovider
 * @copyright   2019 Michael Gardener <mgardener@cissq.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'enrol_poodllprovider_manage_course_module' => array(
        'classname' => 'enrol_poodllprovider\external',
        'methodname' => 'manage_course_module',
        'classpath' => '',
        'description' => '',
        'type' => 'write',
        'ajax' => true
    ),
    'enrol_poodllprovider_delete_modules' => array(
        'classname' => 'enrol_poodllprovider\external',
        'methodname' => 'delete_modules',
        'classpath' => '',
        'description' => 'Deletes all specified module instances',
        'type' => 'write',
        'ajax' => true
    ),
);