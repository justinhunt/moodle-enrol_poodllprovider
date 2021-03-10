<?php
/**
 * Created by PhpStorm.
 * User: ishineguy
 * Date: 3/10/21
 * Time: 21:31
 */

namespace enrol_poodllprovider;


///////////////////////////////////////////////////////////////////////////
//                                                                       //
// This file is part of Moodle - http://moodle.org/                      //
// Moodle - Modular Object-Oriented Dynamic Learning Environment         //
//                                                                       //
// Moodle is free software: you can redistribute it and/or modify        //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation, either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// Moodle is distributed in the hope that it will be useful,             //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details.                          //
//                                                                       //
// You should have received a copy of the GNU General Public License     //
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.       //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Short mod form for selected activities
 *
 * @package    enrol_poodllprovider
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 2021 onwards Justin Hunt  http://poodll.com
 */

require_once($CFG->libdir . '/formslib.php');


/**
 *
 *
 * Module short form
 *
 * @abstract
 * @copyright  2021 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class shortmodform extends \moodleform {


    /**
     * Add the required basic elements to the form.
     *
     */
    public final function definition() {

        $mform = $this->_form;

        // Adding the standard "name" field
        $name = 'name';
        $label = get_string('name');
        $mform->addElement('text', $name, $label, array('size'=>'64'));
        $mform->setType($name, PARAM_TEXT);
        $mform->addRule($name, null, 'required', null, 'client');
        $mform->addRule($name, get_string('maximumchars', null, 255), 'maxlength', 255, 'client');

        $mform->addElement('hidden', 'modulename');
        $mform->setType('modulename', PARAM_TEXT);

        $mform->addElement('hidden', 'add');
        $mform->setType('add', PARAM_TEXT);

        $mform->addElement('hidden', 'return');
        $mform->setType('return', PARAM_INT);

        $mform->addElement('hidden', 'sr');
        $mform->setType('sr', PARAM_INT);

        $mform->addElement('hidden', 'course');
        $mform->setType('course', PARAM_INT);



        //add the action buttons
        $this->add_action_buttons();

    }

}