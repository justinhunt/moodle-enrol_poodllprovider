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
 * This file contains a renderer for the assignment class
 *
 * @package   enrol_poodllprovider
 * @copyright 2021 poodll {@link http://www.poodll.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


namespace enrol_poodllprovider\local\output;
//namespace theme_boost\output;

defined('MOODLE_INTERNAL') || die();

// Be sure to include the H5P renderer so it can be extended
require_once($CFG->dirroot . '/mod/assign/renderer.php');

/**
 * A custom renderer class that extends the assign module renderer
 *
 * @package enrol_poodllprovider
 * @copyright 2021 Poodll {@link http://www.poodll.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_assign_renderer extends \mod_assign_renderer {

    /**
     * Render the header.
     *
     * @param assign_header $header
     * @return string
     */
    public function render_assign_header($header) {
        global $CFG;

        $o = '';

        if ($header->subpage) {
            $this->page->navbar->add($header->subpage);
        }

        $this->page->set_title(get_string('pluginname', 'assign'));
        $this->page->set_heading($this->page->course->fullname);



        $o .= $this->output->header();
        $heading = format_string($header->assign->name, false, array('context' => $header->context));

        $o .= $this->output->heading($heading);
        if (has_capability('mod/assign:addinstance',  $header->context)) {

            $currenttab='view';
            if(!empty($header->subpage)){
                switch(strtolower($header->subpage)){
                    case 'grading':
                        $currenttab='grading';
                        break;
                    default:
                        $currenttab='view';

                }
            }

            if (!empty($currenttab)) {
                ob_start();
                include($CFG->dirroot.'/enrol/poodllprovider/assign/tabs.php');
                $o .= ob_get_contents();
                ob_end_clean();
            }

        }

        if ($header->preface) {
            $o .= $header->preface;
        }

        if ($header->showintro) {
            $o .= $this->output->box_start('generalbox boxaligncenter', 'intro');
            $o .= format_module_intro('assign', $header->assign, $header->coursemoduleid);
            $o .= $header->postfix;
            $o .= $this->output->box_end();
        }

        return $o;
    }
}