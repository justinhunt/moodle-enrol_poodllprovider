/**
 * LTI Provider.
 *
 * @module     enrol_poodllprovider/institution
 * @class      LTI Provider
 * @package    enrol_poodllprovider
 * @copyright  2020 Michael Gardener <mgardener@cissq.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'jquery',
    'jqueryui',
    'core/str',
    'core/ajax',
    'core/templates',
    'core/custom_interaction_events',
    'core/notification',
    'core/modal_factory',
    'core/modal_events',
    'core/yui',
    'enrol_poodllprovider/mform'
], function($, jqui, Str, Ajax, Templates, CustomEvents, Notification, ModalFactory, ModalEvents, Y, MForm) {
    var SELECTORS = {
        MENU_COLLAPSE: '[data-action="collapse"]',
        RETURN_MAIN_MENU: '[data-action="main-menu"]',
        ACTIVITY_SELECT: '[data-action="activity-select"]',
        ACTIVITY_EDIT: '[data-action="activity-edit"]',
        ACTIVITY_DELETE: '[data-action="activity-delete"]',
        ACTIVITY_ADD: '[data-action=activity-add]',
    };

    /**
     * Modal.
     *
     * @param {jQuery} triggerElement
     * @param {Number} contextid
     * @param {Number} itemnumber
     * @param {String} module
     * @param {String} title
     * @return {MForm} modal
     */
    var showModalForm = function(triggerElement, contextid, itemnumber, module, cmid) {
        var modal = new MForm({
            contextid: contextid,
            itemnumber: itemnumber,
            module: module,
            cmid: cmid,
            triggerElement: triggerElement,
        });
        return modal;
    };

    /**
     * Listen to and handle events for menu actions.
     *
     * @param {Object} root Aside container element.
     */
    var registerEventListeners = function(root) {
        CustomEvents.define(root, [
            CustomEvents.events.activate
        ]);

        root.on(CustomEvents.events.activate, SELECTORS.MENU_COLLAPSE, function(e, data) {
            data.originalEvent.preventDefault();
            var module = $(this).data('module');
            $('#collapse-' + module).collapse('toggle');
            $('#top-menu').hide();
        });

        root.on(CustomEvents.events.activate, SELECTORS.RETURN_MAIN_MENU, function(e, data) {
            data.originalEvent.preventDefault();
            var module = $(this).data('module');
            $('#collapse-' + module).collapse('toggle');
            $('#top-menu').show();
        });

        root.on(CustomEvents.events.activate, SELECTORS.ACTIVITY_EDIT, function(e, data) {
            data.originalEvent.preventDefault();
            var module = $(this).data('module');
            var contextid = $(this).data('contextid');
            var itemnumber = $(this).data('itemnumber');
            var cmid = $(this).data('cmid');

            var triggerElement = $(e.currentTarget);
            showModalForm(triggerElement, contextid, itemnumber, module, cmid);
        });

        root.on(CustomEvents.events.activate, SELECTORS.ACTIVITY_DELETE, function(e, data) {
            data.originalEvent.preventDefault();
            //var module = $(this).data('module');
            //var contextid = $(this).data('contextid');
            //var itemnumber = $(this).data('itemnumber');
            var cmid = $(this).data('cmid');

            ModalFactory.create({
                type: ModalFactory.types.SAVE_CANCEL,
                title: 'Delete',
                body: 'Do you really want to delete?',
            })
            .then(function(modal) {
                modal.setSaveButtonText('Delete');
                var root = modal.getRoot();
                root.on(ModalEvents.save, function() {
                    Ajax.call([{
                        methodname: 'enrol_poodllprovider_delete_modules',
                        args: {
                            cmids: [cmid]
                        }
                    }]);
                });
                modal.show();
            });
        });

        root.on(CustomEvents.events.activate, SELECTORS.ACTIVITY_SELECT, function(e, data) {
            data.originalEvent.preventDefault();
            var itemnumber = $(this).data('itemnumber');
            document.getElementById('poodllprovider_item_' + itemnumber).submit();
        });

        root.on(CustomEvents.events.activate, SELECTORS.ACTIVITY_ADD, function(e, data) {
            data.originalEvent.preventDefault();
            var module = $(this).data('module');
            var contextid = $(this).data('contextid');
            var itemnumber = $(this).data('itemnumber');

            var triggerElement = $(e.currentTarget);
            showModalForm(triggerElement, contextid, itemnumber, module, 0);
        });
    };

    /**
     * Initialise all of the modules for the overview block.
     *
     * @param {object} root The root element for the overview block.
     */
    var init = function(root) {
        root = $(root);

        if (!root.attr('data-init')) {
            registerEventListeners(root);
            root.attr('data-init', true);
        }
    };

    return {
        init: init
    };
});