/**
 * LTI Provider.
 *
 * @module     enrol_poodllprovider/mform
 * @class      LTI Provider
 * @package    enrol_poodllprovider
 * @copyright  2020 Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'core/modal_factory',
    'core/modal_events',
    'core/ajax',
    'core/notification',
    'core/yui',
    'core/event',
    'core/str',
    'core/fragment',
], function($, ModalFactory, ModalEvents, Ajax, Notification, Y, Event, Str, Fragment) {
    /**
     * Constructor
     *
     * @param {Object} data used to find triggers for the new group modal.
     *
     * Each call to init gets it's own instance of this class.
     */
    var MForm = function(data) {
        this.data = data;
        this.init();
    };

    /**
     * @var {Modal} modal
     * @private
     */
    MForm.prototype.modal = null;

    /**
     * @var {Object} data
     */
    MForm.prototype.data = {};

    /**
     * Initialise the class.
     *
     * @param {String} selector used to find triggers for the new group modal.
     * @private
     * @return {Promise}
     */
    MForm.prototype.init = function() {
        var requiredStrings = [
            {key: 'pluginname', component: this.data.module},
        ];

        Str.get_strings(requiredStrings)
            .then(function(str) {
                return ModalFactory.create({
                    type: ModalFactory.types.SAVE_CANCEL,
                    title: str[0],
                    body: ''
                }, this.data.triggerElement);
            }.bind(this))
            .then(function(modal) {
                // Keep a reference to the modal.
                this.modal = modal;

                // We need to make sure that the modal already exists when we render the form. Some form elements
                // such as date_selector inspect the existing elements on the page to find the highest z-index.
                this.modal.setBody(this.getBody(this.data.contextid, this.data.module, this.data.cmid, this.data.formdata));

                // Forms are big, we want a big modal.
                this.modal.setLarge();

                // We want to reset the form every time it is opened.
                this.modal.getRoot().on(ModalEvents.hidden, function() {
                    this.modal.destroy();
                    this.resetDirtyFormState();
                }.bind(this));

                // We want to hide the submit buttons every time it is opened.
                this.modal.getRoot().on(ModalEvents.shown, function() {
                    this.modal.getRoot().append('<style>[data-fieldtype=submit] { display: none ! important; }</style>');
                }.bind(this));

                // We catch the modal save event, and use it to submit the form inside the modal.
                // Triggering a form submission will give JS validation scripts a chance to check for errors.
                this.modal.getRoot().on(ModalEvents.save, this.submitForm.bind(this));

                // We also catch the form submit event and use it to submit the form with ajax.
                this.modal.getRoot().on('submit', 'form', this.submitFormAjax.bind(this));

                this.modal.show();
                return this.modal;
            }.bind(this))
            .fail(Notification.exception);
    };

    /**
     * @method getBody
     * @private
     * @return {Promise}
     */
    MForm.prototype.getBody = function(contextid, module, cmid, formdata) {
        if (typeof formdata === "undefined") {
            formdata = {};
        }
        // Get the content of the modal.
        var params = {
            ltimodulename: module,
            cmid: cmid,
            jsonformdata: JSON.stringify(formdata)
        };
        return Fragment.loadFragment('enrol_poodllprovider', 'ltimodule_form', contextid, params);
    };

    /**
     * On form submit. Caller may override
     *
     * @param {Object} response Response received from the form's "process" method
     * @return {Object}
     */
    MForm.prototype.onSubmitSuccess = function(response) {
        // By default this function does nothing. Return here is irrelevant, it is only present to make eslint happy.
        return response;
    };

    /**
     * On form validation error. Caller may override
     *
     * @return {mixed}
     */
    MForm.prototype.onValidationError = function() {
        // By default this function does nothing. Return here is irrelevant, it is only present to make eslint happy.
        return undefined;
    };

    /**
     * On exception during form processing. Caller may override
     *
     * @param {Object} exception
     */
    MForm.prototype.onSubmitError = function(exception) {
        Notification.exception(exception);
    };

    /**
     * Reset "dirty" form state (warning if there are changes)
     */
    MForm.prototype.resetDirtyFormState = function() {
        Y.use('moodle-core-formchangechecker', function() {
            M.core_formchangechecker.reset_form_dirty_state();
        });
    };

    /**
     * Validate form elements
     * @return {boolean} true if client-side validation has passed, false if there are errors
     */
    MForm.prototype.validateElements = function() {
        Event.notifyFormSubmitAjax(this.modal.getRoot().find('form')[0]);

        // Now the change events have run, see if there are any "invalid" form fields.
        var invalid = $.merge(
            this.modal.getRoot().find('[aria-invalid="true"]'),
            this.modal.getRoot().find('.error')
        );

        // If we found invalid fields, focus on the first one and do not submit via ajax.
        if (invalid.length) {
            invalid.first().focus();
            return false;
        }

        return true;
    };

    /**
     * Disable buttons during form submission
     */
    MForm.prototype.disableButtons = function() {
        this.modal.getFooter().find('[data-action]').attr('disabled', true);
    };

    /**
     * Enable buttons after form submission (on validation error)
     */
    MForm.prototype.enableButtons = function() {
        this.modal.getFooter().find('[data-action]').removeAttr('disabled');
    };

    /**
     * Private method
     *
     * @method submitFormAjax
     * @private
     * @param {Event} e Form submission event.
     */
    MForm.prototype.submitFormAjax = function(e) {
        // We don't want to do a real form submission.
        e.preventDefault();

        // If we found invalid fields, focus on the first one and do not submit via ajax.
        if (!this.validateElements()) {
            return;
        }
        this.disableButtons();

        var changeEvent = document.createEvent('HTMLEvents');
        changeEvent.initEvent('change', true, true);

        // Prompt all inputs to run their validation functions.
        // Normally this would happen when the form is submitted, but
        // since we aren't submitting the form normally we need to run client side
        // validation.
        this.modal.getRoot().find(':input').each(function(index, element) {
            element.dispatchEvent(changeEvent);
        });

        // Convert all the form elements values to a serialised string.
        var formData = this.modal.getRoot().find('form').serialize();

        Ajax.call([{
            methodname: 'enrol_poodllprovider_manage_course_module',
            args: {
                contextid: this.data.contextid,
                itemnumber: this.data.itemnumber,
                cmid: this.data.cmid,
                jsonformdata: JSON.stringify(formData)
            },
            done: this.handleFormSubmissionResponse.bind(this, formData),
            fail: this.handleFormSubmissionFailure.bind(this, formData)
        }])[0]
        .then(function(data) {
            if (data !== '') {
                //$('section.activity-list.sections.collapse.show .row.activity:last').after(data);
                $('section.activity-list.sections.collapse.show .enrol_poodllprovider_activityitems').prepend(data);
            }
        });
    };

    /**
     * @method handleFormSubmissionResponse
     * @private
     * @return {Promise}
     */
    MForm.prototype.handleFormSubmissionResponse = function() {
        this.modal.hide();
        this.resetDirtyFormState();
    };

    /**
     * @method handleFormSubmissionFailure
     * @private
     * @return {Promise}
     */
    MForm.prototype.handleFormSubmissionFailure = function(data) {
        // Oh noes! Epic fail :(
        // Ah wait - this is normal. We need to re-display the form with errors!
        this.modal.setBody(this.getBody(data));
    };

    /**
     * This triggers a form submission, so that any mform elements can do final tricks before the form submission is processed.
     *
     * @method submitForm
     * @param {Event} e Form submission event.
     * @private
     */
    MForm.prototype.submitForm = function(e) {
        e.preventDefault();
        this.modal.getRoot().find('form').submit();
    };

    return MForm;
});