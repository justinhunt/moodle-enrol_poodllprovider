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
 * poodllprovider enrolment plugin helper.
 *
 * @package enrol_poodllprovider
 * @copyright 2020 Justin Hunt <justin@poodll.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_poodllprovider;

defined('MOODLE_INTERNAL') || die();

/**
 * poodllprovider enrolment plugin helper class.
 *
 * @package enrol_poodllprovider
 * @copyright 2020 Justin Hunt <justin@poodll.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /*
     * The value used when we want to enrol new members and unenrol old ones.
     */
    const MEMBER_SYNC_ENROL_AND_UNENROL = 1;

    /*
     * The value used when we want to enrol new members only.
     */
    const MEMBER_SYNC_ENROL_NEW = 2;

    /*
     * The value used when we want to unenrol missing users.
     */
    const MEMBER_SYNC_UNENROL_MISSING = 3;

    /**
     * Code for when an enrolment was successful.
     */
    const ENROLMENT_SUCCESSFUL = true;

    /**
     * Error code for enrolment when max enrolled reached.
     */
    const ENROLMENT_MAX_ENROLLED = 'maxenrolledreached';

    /**
     * Error code for enrolment has not started.
     */
    const ENROLMENT_NOT_STARTED = 'enrolmentnotstarted';

    /**
     * Error code for enrolment when enrolment has finished.
     */
    const ENROLMENT_FINISHED = 'enrolmentfinished';

    /**
     * Error code for when an image file fails to upload.
     */
    const PROFILE_IMAGE_UPDATE_SUCCESSFUL = true;

    /**
     * Error code for when an image file fails to upload.
     */
    const PROFILE_IMAGE_UPDATE_FAILED = 'profileimagefailed';

    /**
     * Creates a unique username.
     *
     * @param string $consumerkey Consumer key
     * @param string $ltiuserid External tool user id
     * @return string The new username
     */
    public static function create_username($consumerkey, $ltiuserid) {
        if (!empty($ltiuserid) && !empty($consumerkey)) {
            $userkey = $consumerkey . ':' . $ltiuserid;
        } else {
            $userkey = false;
        }

        return 'enrol_poodllprovider' . sha1($consumerkey . '::' . $userkey);
    }

    /**
     * Adds default values for the user object based on the tool provided.
     *
     * @param \stdClass $tool
     * @param \stdClass $user
     * @return \stdClass The $user class with added default values
     */
    public static function assign_user_tool_data($tool, $user) {
        global $CFG;

        $user->city = (!empty($tool->city)) ? $tool->city : "";
        $user->country = (!empty($tool->country)) ? $tool->country : "";
        $user->institution = (!empty($tool->institution)) ? $tool->institution : "";
        $user->timezone = (!empty($tool->timezone)) ? $tool->timezone : "";
        if (isset($tool->maildisplay)) {
            $user->maildisplay = $tool->maildisplay;
        } else if (isset($CFG->defaultpreference_maildisplay)) {
            $user->maildisplay = $CFG->defaultpreference_maildisplay;
        } else {
            $user->maildisplay = 2;
        }
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->confirmed = 1;
        $user->lang = $tool->lang;

        return $user;
    }

    /**
     * Compares two users.
     *
     * @param \stdClass $newuser The new user
     * @param \stdClass $olduser The old user
     * @return bool True if both users are the same
     */
    public static function user_match($newuser, $olduser) {
        if ($newuser->firstname != $olduser->firstname) {
            return false;
        }
        if ($newuser->lastname != $olduser->lastname) {
            return false;
        }
        if ($newuser->email != $olduser->email) {
            return false;
        }
        if ($newuser->city != $olduser->city) {
            return false;
        }
        if ($newuser->country != $olduser->country) {
            return false;
        }
        if ($newuser->institution != $olduser->institution) {
            return false;
        }
        if ($newuser->timezone != $olduser->timezone) {
            return false;
        }
        if ($newuser->maildisplay != $olduser->maildisplay) {
            return false;
        }
        if ($newuser->mnethostid != $olduser->mnethostid) {
            return false;
        }
        if ($newuser->confirmed != $olduser->confirmed) {
            return false;
        }
        if ($newuser->lang != $olduser->lang) {
            return false;
        }

        return true;
    }

    /**
     * Updates the users profile image.
     *
     * @param int $userid the id of the user
     * @param string $url the url of the image
     * @return bool|string true if successful, else a string explaining why it failed
     */
    public static function update_user_profile_image($userid, $url) {
        global $CFG, $DB;

        require_once($CFG->libdir . '/filelib.php');
        require_once($CFG->libdir . '/gdlib.php');

        $fs = get_file_storage();

        $context = \context_user::instance($userid, MUST_EXIST);
        $fs->delete_area_files($context->id, 'user', 'newicon');

        $filerecord = array(
            'contextid' => $context->id,
            'component' => 'user',
            'filearea' => 'newicon',
            'itemid' => 0,
            'filepath' => '/'
        );

        $urlparams = array(
            'calctimeout' => false,
            'timeout' => 5,
            'skipcertverify' => true,
            'connecttimeout' => 5
        );

        try {
            $fs->create_file_from_url($filerecord, $url, $urlparams);
        } catch (\file_exception $e) {
            return get_string($e->errorcode, $e->module, $e->a);
        }

        $iconfile = $fs->get_area_files($context->id, 'user', 'newicon', false, 'itemid', false);

        // There should only be one.
        $iconfile = reset($iconfile);

        // Something went wrong while creating temp file - remove the uploaded file.
        if (!$iconfile = $iconfile->copy_content_to_temp()) {
            $fs->delete_area_files($context->id, 'user', 'newicon');
            return self::PROFILE_IMAGE_UPDATE_FAILED;
        }

        // Copy file to temporary location and the send it for processing icon.
        $newpicture = (int) process_new_icon($context, 'user', 'icon', 0, $iconfile);
        // Delete temporary file.
        @unlink($iconfile);
        // Remove uploaded file.
        $fs->delete_area_files($context->id, 'user', 'newicon');
        // Set the user's picture.
        $DB->set_field('user', 'picture', $newpicture, array('id' => $userid));
        return self::PROFILE_IMAGE_UPDATE_SUCCESSFUL;
    }

    /**
     * Enrol a user in a course.
     *
     * @param \stdclass $tool The tool object (retrieved using self::get_lti_tool() or self::get_lti_tools())
     * @param int $userid The user id
     * @return bool|string returns true if successful, else an error code
     */
    public static function enrol_user($tool, $userid) {
        global $DB;

        // Check if the user enrolment exists.
        if (!$DB->record_exists('user_enrolments', array('enrolid' => $tool->enrolid, 'userid' => $userid))) {
            // Check if the maximum enrolled limit has been met.
            if ($tool->maxenrolled) {
                if ($DB->count_records('user_enrolments', array('enrolid' => $tool->enrolid)) >= $tool->maxenrolled) {
                    return self::ENROLMENT_MAX_ENROLLED;
                }
            }
            // Check if the enrolment has not started.
            if ($tool->enrolstartdate && time() < $tool->enrolstartdate) {
                return self::ENROLMENT_NOT_STARTED;
            }
            // Check if the enrolment has finished.
            if ($tool->enrolenddate && time() > $tool->enrolenddate) {
                return self::ENROLMENT_FINISHED;
            }

            $timeend = 0;
            if ($tool->enrolperiod) {
                $timeend = time() + $tool->enrolperiod;
            }

            // Finally, enrol the user.
            $instance = new \stdClass();
            $instance->id = $tool->enrolid;
            $instance->courseid = $tool->courseid;
            $instance->enrol = 'poodllprovider';
            $instance->status = $tool->status;
            $poodllproviderenrol = enrol_get_plugin('poodllprovider');

            // Hack - need to do this to workaround DB caching hack. See MDL-53977.
            $timestart = intval(substr(time(), 0, 8) . '00') - 1;
            $poodllproviderenrol->enrol_user($instance, $userid, null, $timestart, $timeend);
        }

        return self::ENROLMENT_SUCCESSFUL;
    }

    /**
     * Returns the LTI tool.
     *
     * @param int $toolid
     * @return \stdClass the tool
     */
    public static function get_lti_tool($toolid) {
        global $DB;

        $sql = "SELECT elt.*, e.name, e.courseid, e.status, e.enrolstartdate, e.enrolenddate, e.enrolperiod
                  FROM {enrol_pp_tools} elt
                  JOIN {enrol} e
                    ON elt.enrolid = e.id
                 WHERE elt.id = :tid";

        return $DB->get_record_sql($sql, array('tid' => $toolid), MUST_EXIST);
    }

    /**
     * Returns the LTI tools requested.
     *
     * @param array $params The list of SQL params (eg. array('columnname' => value, 'columnname2' => value)).
     * @param int $limitfrom return a subset of records, starting at this point (optional).
     * @param int $limitnum return a subset comprising this many records in total
     * @return array of tools
     */
    public static function get_lti_tools($params = array(), $limitfrom = 0, $limitnum = 0) {
        global $DB;

        $sql = "SELECT elt.*, e.name, e.courseid, e.status, e.enrolstartdate, e.enrolenddate, e.enrolperiod
                  FROM {enrol_pp_tools} elt
                  JOIN {enrol} e
                    ON elt.enrolid = e.id";
        if ($params) {
            $where = "WHERE";
            foreach ($params as $colname => $value) {
                $sql .= " $where $colname = :$colname";
                $where = "AND";
            }
        }
        $sql .= " ORDER BY elt.timecreated";

        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * @param $toolid
     * @return mixed
     */
    public static function render_lti_tool_item($toolid) {
        global $DB, $OUTPUT, $SESSION;

        $consumerkey = $SESSION->lti_posted_data['oauth_consumer_key'];
        $ltiversion = $SESSION->lti_posted_data['lti_version'];

        $tool = \enrol_poodllprovider\helper::get_lti_tool($toolid);
        $toolprovider = new \enrol_poodllprovider\tool_provider($toolid);

        // Special handling for LTIv1 launch requests.
        if ($ltiversion === \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1) {
            $dataconnector = new \enrol_poodllprovider\data_connector();
            $consumer = new \IMSGlobal\LTI\ToolProvider\ToolConsumer($consumerkey, $dataconnector);
            // Check if the consumer has already been registered to the enrol_pp_lti2_consumer table. Register if necessary.
            $consumer->ltiVersion = \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1;
            // For LTIv1, set the tool secret as the consumer secret.
            $consumer->secret = $tool->secret;
            $consumer->name = $SESSION->lti_posted_data['tool_consumer_instance_name'] ?? '';
            $consumer->consumerName = $consumer->name;
            $consumer->consumerGuid = $SESSION->lti_posted_data['tool_consumer_instance_guid'] ?? null;
            $consumer->consumerVersion = $SESSION->lti_posted_data['tool_consumer_info_version'] ?? null;
            $consumer->enabled = true;
            $consumer->protected = true;
            $consumer->save();

            // Set consumer to tool provider.
            $toolprovider->consumer = $consumer;
            // Map tool consumer and published tool, if necessary.
            $toolprovider->map_tool_to_consumer();
        }

        $data = $toolprovider->get_lti_tool_data();

        return $OUTPUT->render_from_template('enrol_poodllprovider/contentitemform', $data);
    }

    /**
     * Returns the number of LTI tools.
     *
     * @param array $params The list of SQL params (eg. array('columnname' => value, 'columnname2' => value)).
     * @return int The number of tools
     */
    public static function count_lti_tools($params = array()) {
        global $DB;

        $sql = "SELECT COUNT(*)
                  FROM {enrol_pp_tools} elt
                  JOIN {enrol} e
                    ON elt.enrolid = e.id";
        if ($params) {
            $where = "WHERE";
            foreach ($params as $colname => $value) {
                $sql .= " $where $colname = :$colname";
                $where = "AND";
            }
        }

        return $DB->count_records_sql($sql, $params);
    }

    /**
     * Create a IMS POX body request for sync grades.
     *
     * @param string $source Sourceid required for the request
     * @param float $grade User final grade
     * @return string
     */
    public static function create_service_body($source, $grade) {
        return '<?xml version="1.0" encoding="UTF-8"?>
            <imsx_POXEnvelopeRequest xmlns="http://www.imsglobal.org/services/ltiv1p1/xsd/imsoms_v1p0">
              <imsx_POXHeader>
                <imsx_POXRequestHeaderInfo>
                  <imsx_version>V1.0</imsx_version>
                  <imsx_messageIdentifier>' . (time()) . '</imsx_messageIdentifier>
                </imsx_POXRequestHeaderInfo>
              </imsx_POXHeader>
              <imsx_POXBody>
                <replaceResultRequest>
                  <resultRecord>
                    <sourcedGUID>
                      <sourcedId>' . $source . '</sourcedId>
                    </sourcedGUID>
                    <result>
                      <resultScore>
                        <language>en-us</language>
                        <textString>' . $grade . '</textString>
                      </resultScore>
                    </result>
                  </resultRecord>
                </replaceResultRequest>
              </imsx_POXBody>
            </imsx_POXEnvelopeRequest>';
    }

    /**
     * Returns the url to launch the lti tool.
     *
     * @param int $toolid the id of the shared tool
     * @return \moodle_url the url to launch the tool
     * @since Moodle 3.2
     */
    public static function get_launch_url($toolid) {
        return new \moodle_url('/enrol/poodllprovider/tool.php', array('id' => $toolid));
    }

    /**
     * Returns the url to get selectable items.
     *
     * @param int $toolid the id of the shared tool
     * @return \moodle_url the url to launch the tool
     * @since Moodle 3.2
     */
    public static function get_contentitemselection_url($toolid) {
        return new \moodle_url('/enrol/poodllprovider/tool.php', array('id' => $toolid));
    }


    /**
     * Returns the name of the poodllprovider enrolment instance, or the name of the course/module being shared.
     *
     * @param \stdClass $tool The lti tool
     * @return string The name of the tool
     * @since Moodle 3.2
     */
    public static function get_name($tool) {
        $name = null;

        if (empty($tool->name)) {
            $toolcontext = \context::instance_by_id($tool->contextid);
            $name = $toolcontext->get_context_name();
        } else {
            $name = $tool->name;
        };

        return $name;
    }

    /**
     * Returns a description of the course or module that this lti instance points to.
     *
     * @param \stdClass $tool The lti tool
     * @return string A description of the tool
     * @since Moodle 3.2
     */
    public static function get_description($tool) {
        global $DB;
        $description = '';
        $context = \context::instance_by_id($tool->contextid);
        if ($context->contextlevel == CONTEXT_COURSE) {
            $course = $DB->get_record('course', array('id' => $context->instanceid));
            $description = $course->summary;
        } else if ($context->contextlevel == CONTEXT_MODULE) {
            $cmid = $context->instanceid;
            $cm = get_coursemodule_from_id(false, $context->instanceid, 0, false, MUST_EXIST);
            $module = $DB->get_record($cm->modname, array('id' => $cm->instance));
            $description = $module->intro;
        }
        return trim(html_to_text($description));
    }

    /**
     * Returns the icon of the tool.
     *
     * @param \stdClass $tool The lti tool
     * @return \moodle_url A url to the icon of the tool
     * @since Moodle 3.2
     */
    public static function get_icon($tool) {
        global $OUTPUT;
        return $OUTPUT->favicon();
    }

    /**
     * Returns the url to the cartridge representing the tool.
     *
     * If you have slash arguments enabled, this will be a nice url ending in cartridge.xml.
     * If not it will be a php page with some parameters passed.
     *
     * @param \stdClass $tool The lti tool
     * @return string The url to the cartridge representing the tool
     * @since Moodle 3.2
     */
    public static function get_cartridge_url($tool) {
        global $CFG;
        $url = null;

        $id = $tool->id;
        $token = self::generate_cartridge_token($tool->id);
        if ($CFG->slasharguments) {
            $url = new \moodle_url('/enrol/poodllprovider/cartridge.php/' . $id . '/' . $token . '/cartridge.xml');
        } else {
            $url = new \moodle_url('/enrol/poodllprovider/cartridge.php',
                    array(
                        'id' => $id,
                        'token' => $token
                    )
                );
        }
        return $url;
    }

    /**
     * Returns the url to the tool proxy registration url.
     *
     * If you have slash arguments enabled, this will be a nice url ending in cartridge.xml.
     * If not it will be a php page with some parameters passed.
     *
     * @param \stdClass $tool The lti tool
     * @return string The url to the cartridge representing the tool
     */
    public static function get_proxy_url($tool) {
        global $CFG;
        $url = null;

        $id = $tool->id;
        $token = self::generate_proxy_token($tool->id);
        if ($CFG->slasharguments) {
            $url = new \moodle_url('/enrol/poodllprovider/proxy.php/' . $id . '/' . $token . '/');
        } else {
            $url = new \moodle_url('/enrol/poodllprovider/proxy.php',
                    array(
                        'id' => $id,
                        'token' => $token
                    )
                );
        }
        return $url;
    }

    /**
     * Returns a unique hash for this site and this enrolment instance.
     *
     * Used to verify that the link to the cartridge has not just been guessed.
     *
     * @param int $toolid The id of the shared tool
     * @return string MD5 hash of combined site ID and enrolment instance ID.
     * @since Moodle 3.2
     */
    public static function generate_cartridge_token($toolid) {
        $siteidentifier = get_site_identifier();
        $checkhash = md5($siteidentifier . '_enrol_poodllprovider_cartridge_' . $toolid);
        return $checkhash;
    }

    /**
     * Returns a unique hash for this site and this enrolment instance.
     *
     * Used to verify that the link to the proxy has not just been guessed.
     *
     * @param int $toolid The id of the shared tool
     * @return string MD5 hash of combined site ID and enrolment instance ID.
     * @since Moodle 3.2
     */
    public static function generate_proxy_token($toolid) {
        $siteidentifier = get_site_identifier();
        $checkhash = md5($siteidentifier . '_enrol_poodllprovider_proxy_' . $toolid);
        return $checkhash;
    }

    /**
     * Verifies that the given token matches the cartridge token of the given shared tool.
     *
     * @param int $toolid The id of the shared tool
     * @param string $token hash for this site and this enrolment instance
     * @return boolean True if the token matches, false if it does not
     * @since Moodle 3.2
     */
    public static function verify_cartridge_token($toolid, $token) {
        return $token == self::generate_cartridge_token($toolid);
    }

    /**
     * Verifies that the given token matches the proxy token of the given shared tool.
     *
     * @param int $toolid The id of the shared tool
     * @param string $token hash for this site and this enrolment instance
     * @return boolean True if the token matches, false if it does not
     * @since Moodle 3.2
     */
    public static function verify_proxy_token($toolid, $token) {
        return $token == self::generate_proxy_token($toolid);
    }

    /**
     * Returns the parameters of the cartridge as an associative array of partial xpath.
     *
     * @param int $toolid The id of the shared tool
     * @return array Recursive associative array with partial xpath to be concatenated into an xpath expression
     *     before setting the value.
     * @since Moodle 3.2
     */
    protected static function get_cartridge_parameters($toolid) {
        global $PAGE, $SITE;
        $PAGE->set_context(\context_system::instance());

        // Get the tool.
        $tool = self::get_lti_tool($toolid);

        // Work out the name of the tool.
        $title = self::get_name($tool);
        $launchurl = self::get_launch_url($toolid);
        $launchurl = $launchurl->out(false);
        $contentitemselectionurl = self::get_contentitemselection_url($toolid);
        $contentitemselectionurl = $contentitemselectionurl->out(false);
        $iconurl = self::get_icon($tool);
        $iconurl = $iconurl->out(false);
        $securelaunchurl = null;
        $secureiconurl = null;
        $vendorurl = new \moodle_url('/');
        $vendorurl = $vendorurl->out(false);
        $description = self::get_description($tool);

        // If we are a https site, we can add the launch url and icon urls as secure equivalents.
        if (\is_https()) {
            $securelaunchurl = $launchurl;
            $secureiconurl = $iconurl;
        }

        return array(
                "/cc:cartridge_basiclti_link" => array(
                    "/blti:title" => $title,
                    "/blti:description" => $description,
                    "/blti:extensions" => array(
                            "/lticm:property[@name='icon_url']" => $iconurl,
                            "/lticm:property[@name='secure_icon_url']" => $secureiconurl,
                            "/lticm:options"=>array(
                                    "/lticm:property[@name='url']" => $contentitemselectionurl, //canvas contentitemselection url
                                    "/lticm:property[@name='icon_url']" => $iconurl
                            )
                        ),
                    "/blti:launch_url" => $launchurl,
                    "/blti:secure_launch_url" => $securelaunchurl,
                    "/blti:icon" => $iconurl,
                    "/blti:secure_icon" => $secureiconurl,
                    "/blti:vendor" => array(
                            "/lticp:code" => $SITE->shortname,
                            "/lticp:name" => $SITE->fullname,
                            "/lticp:description" => trim(html_to_text($SITE->summary)),
                            "/lticp:url" => $vendorurl
                        )
                )
            );
    }

    /**
     * Traverses a recursive associative array, setting the properties of the corresponding
     * xpath element.
     *
     * @param \DOMXPath $xpath The xpath with the xml to modify
     * @param array $parameters The array of xpaths to search through
     * @param string $prefix The current xpath prefix (gets longer the deeper into the array you go)
     * @return void
     * @since Moodle 3.2
     */
    protected static function set_xpath($xpath, $parameters, $prefix = '') {
        foreach ($parameters as $key => $value) {
            if (is_array($value)) {
                self::set_xpath($xpath, $value, $prefix . $key);
            } else {
                $result = @$xpath->query($prefix . $key);
                if ($result) {
                    $node = $result->item(0);
                    if ($node) {
                        if (is_null($value)) {
                            $node->parentNode->removeChild($node);
                        } else {
                            $node->nodeValue = s($value);
                        }
                    }
                } else {
                    throw new \coding_exception('Please check your XPATH and try again.');
                }
            }
        }
    }

    /**
     * Create an IMS cartridge for the tool.
     *
     * @param int $toolid The id of the shared tool
     * @return string representing the generated cartridge
     * @since Moodle 3.2
     */
    public static function create_cartridge($toolid) {
        $cartridge = new \DOMDocument();
        $cartridge->load(realpath(__DIR__ . '/../xml/imslticc.xml'));
        $xpath = new \DOMXpath($cartridge);
        $xpath->registerNamespace('cc', 'http://www.imsglobal.org/xsd/imslticc_v1p0');
        $parameters = self::get_cartridge_parameters($toolid);
        self::set_xpath($xpath, $parameters);

        return $cartridge->saveXML();
    }

    public static function fetch_modtypes(){
        global $DB;
        $choices =array();
        //for pulling all from DB
        //$choicekeys =$DB->get_records('modules', array(), 'name ASC') ;

        //for using the ones we prepared. You also need to add new ones to mod_forms.php
        $choicekeys =['assign','book','chat','choice','englishcentral','feedback','forum','glossary','label','lesson','minilesson',
                'page', 'quiz','readaloud','solo','survey','wiki','wordcards','workshop'];

        foreach ( $choicekeys as $module) {
            $choices[$module] = get_string('modulename', $module);
        }
        return $choices;

    }


    /*
     *  Pad out the almost empty form data from the activity short form for Poodll mods with default data;
     *
     */
    public static function fetch_extrafields($fromform,$course){

        global $DB;
        $themodule =$DB->get_record('modules', array('name'=>$fromform->modulename)) ;

        switch ($fromform->modulename){
            case 'readaloud':
                $fromform->intro='';
                $fromform->timelimit=60;
                $fromform->passage_editor=['text'=>'','format'=>0];
                $fromform->alternatives='';
                $fromform->welcome_editor=['text'=>'Please read the following passage aloud.','format'=>0];
                $fromform->feedback_editor=['text'=>'Thanks for reading. Please be patient until your attempt has been evaluated.','format'=>0];
                $fromform->targetwpm=100;
                $fromform->allowearlyexit=0;
                $fromform->enablepreview=1;
                $fromform->enableshadow=0;
                $fromform->enablelandr=1;
                $fromform->maxattempts=0;
                $fromform->sessionscoremethod=0;
                $fromform->machgrademethod=1;
                $fromform->recorder='once';
                $fromform->enableai=1;
                $fromform->ttslanguage='en-US';
                $fromform->ttsvoice='Amy';
                $fromform->ttsspeed=1;
                $fromform->transcriber=1;
                $fromform->region='useast1';
                $fromform->expiredays=365;
                $fromform->accadjustmethod=0;
                $fromform->accadjust=0;
                $fromform->submitrawaudio=0;
                $fromform->stricttranscribe=0;
                $fromform->activitylink=0;
                $fromform->humanpostattempt=2;

                break;

            case "wordcards":
                $fromform->intro='';
                $fromform->introformat=0;
                $fromform->ttslanguage='en-US';
                $fromform->step1practicetype=1;
                $fromform->step1termcount=4;
                $fromform->step2practicetype=0;
                $fromform->step3practicetype=0;
                $fromform->step4practicetype=0;
                $fromform->step5practicetype=0;

                $fromform->maxattempts=0;
                $fromform->skipreview=0;
                $fromform->finishedstepmsg_editor=['text'=>'<h4 style="text-align: center;">Congratulations!</h4>','format'=>0];
                $fromform->completedmsg_editor=['text'=>'You have completed this activity. Feel free to go back and practice more!','format'=>0];
                $fromform->modulename = 'wordcards';
                $fromform->add ='wordcards';
                break;

            case 'solo':
                $fromform->intro='';
                $fromform->speakingtopic='';
                $fromform->addmedia=1;
                $fromform->addiframe='';
                $fromform->addttsaudio='';
                $fromform->topicmedia=0;
                $fromform->topicttsvoice='Amy';
                $fromform->recordertype='audio';
                $fromform->recorderskin='once';
                $fromform->convlength=2;
                $fromform->maxconvlength=2;
                $fromform->targetwords='';
                $fromform->gradewordgoal=60;

                $fromform->enabletranscription=1;
                $fromform->enableai=1;
                $fromform->ttslanguage='en-US';
                $fromform->transcriber=1;
                $fromform->region='useast1';
                $fromform->expiredays=365;
                $fromform->multiattempts=0;
                $fromform->postattemptedit=0;
                $fromform->enableautograde=1;
                $fromform->gradewordcount='totalunique';
                $fromform->gradeoptions=2;

                $fromform->gradebasescore='';
                $fromform->graderatioitem='';
                $fromform->bonusdirection1='plus';
                $fromform->bonuspoints1=3;
                $fromform->bonus1='targetwordspoken';

                $fromform->bonusdirection2='plus';
                $fromform->bonuspoints2=3;
                $fromform->bonus2='--';

                $fromform->bonusdirection3='plus';
                $fromform->bonuspoints3=3;
                $fromform->bonus3='--';

                $fromform->bonusdirection4='plus';
                $fromform->bonuspoints4=3;
                $fromform->bonus4='--';

                $fromform->tips_editor=['text'=>'Speak simply and clearly.','format'=>0];
                break;


            case 'pchat':
                $fromform->intro='';
                $fromform->multiattempts=0;
                $fromform->postattemptedit=0;
                $fromform->convlength=7;
                $fromform->userconvlength=1;
                $fromform->revq1='';
                $fromform->revq2='';
                $fromform->revq3='';
                $fromform->enableai=1;
                $fromform->ttslanguage='en-US';
                $fromform->transcriber=1;
                $fromform->region='useast1';
                $fromform->expiredays=365;
                $fromform->enableautograde=1;
                $fromform->advancedgradingmethod_pchat='';
                $fromform->tips_editor=['text'=>'Speak simply and clearly.','format'=>0];
                break;

            case 'minilesson':
                $fromform->intro='';
                $fromform->pagelayout='standard';
                $fromform->timelimit=0;
                $fromform->showqtitles=0;
                $fromform->maxattempts=0;
                $fromform->ttslanguage='en-US';
                $fromform->region='useast1';
                $fromform->richtextprompt=0;
                $fromform->activitylink=0;
                break;

            case 'englishcentral':
                $fromform->intro='';
                $fromform->introformat=0;
                $fromform->activityopen=0;
                $fromform->videoopen=0;
                $fromform->videoclose=0;
                $fromform->activityclose=0;
                $fromform->watchgoal=10;
                $fromform->learngoal=20;
                $fromform->speakgoal=10;
                $fromform->studygoal=90;
                break;

            default:
        }

        //add the common bits
        $fromform->add =$fromform->modulename;
        $fromform->grade=100;
        $fromform->gradecat=1;
        $fromform->grade_rescalegrades=null;
        $fromform->gradepass=null;
        $fromform->gradeoptions=2;
        $fromform->visible=1;
        $fromform->visibleoncoursepage= 1;
        $fromform->cmidnumber = '';
        $fromform->groupmode =0;
        $fromform->groupingid =0;
        $fromform->availabilityconditionsjson = '{"op":"&","c":[],"showc":[]}' ;
        $fromform->completionunlocked =1;
        $fromform->completion =1;
        $fromform->completionexpected = 0;
        $fromform->completionmingrade=0;
        $fromform->tags = [];
        $fromform->course = $course->id;
        $fromform->coursemodule = 0;
        $fromform->section = 0;
        $fromform->module = $themodule->id;
        $fromform->instance = 0;
        $fromform->update =0;
        $fromform->return =  0;
        $fromform->sr = 0;
        $fromform->competency_rule =  '0';
        $fromform->submitbutton ='Save and display';

        return $fromform;

    }

}
