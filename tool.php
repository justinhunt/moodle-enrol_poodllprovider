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
 * The main entry point for the external system.
 *
 * @package    enrol_poodllprovider
 * @copyright  2020 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$toolid = required_param('id', PARAM_INT);

$PAGE->set_context(context_system::instance());
$url = new moodle_url('/enrol/poodllprovider/tool.php');
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');
$PAGE->set_title(get_string('opentool', 'enrol_poodllprovider'));

// Get the tool.
$tool = \enrol_poodllprovider\helper::get_lti_tool($toolid);

// Check if the authentication plugin is disabled.
if (!is_enabled_auth('lti')) {
    print_error('pluginnotenabled', 'auth', '', get_string('pluginname', 'auth_lti'));
    exit();
}

// Check if the enrolment plugin is disabled.
if (!enrol_is_enabled('poodllprovider')) {
    print_error('enrolisdisabled', 'enrol_poodllprovider');
    exit();
}

// Check if the enrolment instance is disabled.
if ($tool->status != ENROL_INSTANCE_ENABLED) {
    print_error('enrolisdisabled', 'enrol_poodllprovider');
    exit();
}

$consumerkey = required_param('oauth_consumer_key', PARAM_TEXT);
$ltiversion = optional_param('lti_version', null, PARAM_TEXT);
$messagetype = required_param('lti_message_type', PARAM_TEXT);

// Only accept launch requests from this endpoint.
if ($messagetype != "basic-lti-launch-request" && $messagetype != "ContentItemSelectionRequest"){
    print_error('invalidrequest', 'enrol_poodllprovider');
    exit();
}

// Initialise tool provider.
$toolprovider = new \enrol_poodllprovider\tool_provider($toolid);

// Special handling for LTIv1 launch requests.
if ($ltiversion === \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1) {
    $dataconnector = new \enrol_poodllprovider\data_connector();
    $consumer = new \IMSGlobal\LTI\ToolProvider\ToolConsumer($consumerkey, $dataconnector);
    // Check if the consumer has already been registered to the enrol_pp_lti2_consumer table. Register if necessary.
    $consumer->ltiVersion = \IMSGlobal\LTI\ToolProvider\ToolProvider::LTI_VERSION1;
    // For LTIv1, set the tool secret as the consumer secret.
    $consumer->secret = $tool->secret;
    $consumer->name = optional_param('tool_consumer_instance_name', '', PARAM_TEXT);
    $consumer->consumerName = $consumer->name;
    $consumer->consumerGuid = optional_param('tool_consumer_instance_guid', null, PARAM_TEXT);
    $consumer->consumerVersion = optional_param('tool_consumer_info_version', null, PARAM_TEXT);
    $consumer->enabled = true;
    $consumer->protected = true;
    $consumer->save();

    // Set consumer to tool provider.
    $toolprovider->consumer = $consumer;
    // Map tool consumer and published tool, if necessary.
    $toolprovider->map_tool_to_consumer();
}

// Handle the request.
ob_start();
$toolprovider->handleRequest();
$o = ob_get_contents();
ob_end_clean();

echo $OUTPUT->header();
echo $o;
echo $OUTPUT->footer();
