<?php



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
 * Setup Form for Mod Assignment Activity
 *
 * @package    mod_assign
 * @author     Justin Hunt <poodllsupport@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL
 * @copyright  (C) 1999 onwards Justin Hunt  http://poodll.com
 */



namespace enrol_poodllprovider;

//why do we need to include this?
require_once($CFG->libdir . '/formslib.php');




/**
 * @abstract
 * @copyright  2021 Justin Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class assign_setupform extends \moodleform {

    /**
     * This is used to identify this itemtype.
     * @var string
     */
    public $type;

    /**
     * The simple string that describes the item type e.g. audioitem, textitem
     * @var string
     */
    public $typestring;


    /**
     * An array of options used in the htmleditor
     * @var array
     */
    protected $editoroptions = array();

    /**
     * An array of options used in the filemanager
     * @var array
     */
    protected $filemanageroptions = array();

    /**
     * An array of options used in the filemanager
     * @var array
     */
    protected $moduleinstance = null;


    /**
     * Add the required basic elements to the form.
     *
     * This method adds the basic elements to the form including title and contents
     * and then calls custom_definition();
     */
    public final function definition() {
        global $CFG, $COURSE, $DB;

        $mform = $this->_form;
        $context = $this->_customdata['context'];
        $cm = $this->_customdata['cm'];
        $course = $this->_customdata['course'];



        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('assignmentname', 'assign'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $mform = $this->_form;

        $label = get_string('description', 'assign');
        $mform->addElement('editor', 'introeditor', $label, array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                'noclean' => true, 'context' => $context, 'subdirs' => true));
        $mform->setType('introeditor', PARAM_RAW); // no XSS prevention here, users must be trusted
        $mform->addRule('introeditor', get_string('required'), 'required', null, 'client');


        $mform->addElement('filemanager', 'introattachments',
                get_string('introattachments', 'assign'),
                null, array('subdirs' => 0, 'maxbytes' => $COURSE->maxbytes) );
        $mform->addHelpButton('introattachments', 'introattachments', 'assign');

        $ctx = \context_module::instance($cm->id);

        $assignment = new \assign($ctx, null, null);
        if (!$ctx) {
                $ctx = \context_course::instance($course);
         }
        $assignment->set_course($course);
        $config = get_config('assign');
        $assignment->add_all_plugin_settings($mform);


        //add the action buttons
        $this->add_action_buttons(get_string('cancel'), get_string('savechangesanddisplay'));

    }

    protected final function add_media_upload($name, $count=-1, $label = null, $required = false) {
        if($count>-1){
            $name = $name . $count ;
        }

        $this->_form->addElement('filemanager',
                $name,
                $label,
                null,
                $this->filemanageroptions
        );

    }

    protected final function add_media_prompt_upload($label = null, $required = false) {
        return $this->add_media_upload(constants::AUDIOPROMPT,-1,$label,$required);
    }


    /**
     * Convenience function: Adds an response editor
     *
     * @param int $count The count of the element to add
     * @param string $label, null means default
     * @param bool $required
     * @return void
     */
    protected final function add_editorarearesponse($count, $label = null, $required = false) {
        if ($label === null) {
            $label = get_string('response', constants::M_COMPONENT);
        }
        //edoptions = array('noclean'=>true)
        $this->_form->addElement('editor', constants::TEXTANSWER .$count. '_editor', $label, array('rows'=>'4', 'columns'=>'80'), $this->editoroptions);
        $this->_form->setDefault(constants::TEXTANSWER .$count. '_editor', array('text'=>'', 'format'=>FORMAT_MOODLE));
        if ($required) {
            $this->_form->addRule(constants::TEXTANSWER .$count. '_editor', get_string('required'), 'required', null, 'client');
        }
    }

    /**
     * Any data processing needed before the form is displayed
     * (needed to set up draft areas for editor and filemanager elements)
     * @param array $defaultvalues
     */
    public function data_preprocessing(&$defaultvalues) {
        global $DB;

        $ctx = null;
        if ($this->current && $this->current->coursemodule) {
            $cm = get_coursemodule_from_instance('assign', $this->current->id, 0, false, MUST_EXIST);
            $ctx = context_module::instance($cm->id);
        }
        $assignment = new assign($ctx, null, null);
        if ($this->current && $this->current->course) {
            if (!$ctx) {
                $ctx = context_course::instance($this->current->course);
            }
            $course = $DB->get_record('course', array('id'=>$this->current->course), '*', MUST_EXIST);
            $assignment->set_course($course);
        }

        $draftitemid = file_get_submitted_draft_itemid('introattachments');
        file_prepare_draft_area($draftitemid, $ctx->id, 'mod_assign', ASSIGN_INTROATTACHMENT_FILEAREA,
                0, array('subdirs' => 0));
        $defaultvalues['introattachments'] = $draftitemid;

        $assignment->plugin_data_preprocessing($defaultvalues);
    }



}