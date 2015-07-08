define([
    'jquery', 'underscore', 'backbone', 'oroui/js/modal', 'oroui/js/mediator', 'orotranslation/js/translator', 'routing'
], function($, _, Backbone, modal, mediator, __, routing) {
    'use strict';

    /**
     * @extends oro.Modal
     */
    var WidgetPickerDialog = modal.extend({
        open: function() {
            Backbone.BootstrapModal.prototype.open.apply(this, arguments);
            var addWidgetControls = $('.add-widget-button');
            var collapseControls = $('.dashboard-picker-collapse');

            collapseControls.unbind('click', this.options.collapseCallback);
            collapseControls.bind('click', {}, this.options.collapseCallback);

            addWidgetControls.unbind('click', this.options.clickAddToDashboardCallback);
            addWidgetControls.bind('click', {controls: addWidgetControls}, this.options.clickAddToDashboardCallback);
        }
    });

    /**
     * @export  orodashboard/js/widget-picker
     * @class   orodashboard.WidgetPicker
     */
    return {
        /**
         * @property {integer}
         */
        targetColumn: 0,

        /**
         * @property {Backbone.BootstrapModal}
         */
        dialog: null,

        /**
         * @property {integer}
         */
        dashboardId: null,

        /**
         * @param {integer} dashboardId
         */
        init: function(dashboardId) {
            this.dashboardId = dashboardId;

            this.dialog = new WidgetPickerDialog({
                content: $('#available-dashboard-widgets').html(),
                className: 'modal dashboard-widgets-wrapper',
                title: __('oro.dashboard.add_dashboard_widgets.title'),
                clickAddToDashboardCallback: _.bind(this._onClickAddToDashboard, this),
                collapseCallback: this._collapseDelegate
            });

            $('.dashboard-widgets-add').bind('click', _.bind(this._onClickAddWidget, this));
        },

        /**
         * @private
         */
        _collapseDelegate: function() {
            var $this = $(this);
            var container = $this.parents('.dashboard-widget-container');
            $this.toggleClass('collapsed-state');
            container.find('.dashboard-widgets-description').fadeToggle();
        },

        /**
         * @param {Event} event
         * @private
         */
        _onClickAddToDashboard: function(event) {
            var $control = $(event.target);
            if ($control.hasClass('disabled')) {
                return;
            }
            var widgetContainer = $control.parents('.dashboard-widget-container');
            var controls = event.data.controls;
            var self = this;
            this._startLoading(controls, widgetContainer);
            $.post(
                routing.generate('oro_api_post_dashboard_widget_add_widget'),
                {
                    widgetName: $control.data('widget-name'),
                    dashboardId: this.dashboardId,
                    targetColumn: this.targetColumn
                },
                function(response) {
                    mediator.trigger('dashboard:widget:add', response);
                    self._endLoading(controls, widgetContainer);
                }, 'json'
            );
        },

        /**
         * @param {jQuery} controls collection
         * @param {jQuery} widgetContainer current widget container
         * @private
         */
        _startLoading: function(controls, widgetContainer) {
            controls.addClass('disabled');
            var widgetButtonWrapper = widgetContainer.find('.dashboard-widgets-pick-wrapper');
            widgetButtonWrapper.addClass('loading-content');
            widgetButtonWrapper.find('.add-widget-button').hide();
        },

        /**
         * @param {jQuery} controls collection
         * @param {jQuery} widgetContainer current widget container
         * @private
         */
        _endLoading: function(controls, widgetContainer) {
            controls.removeClass('disabled');
            var widgetButtonWrapper = widgetContainer.find('.dashboard-widgets-pick-wrapper');
            widgetButtonWrapper.removeClass('loading-content');
            //fix case if modal window closed after press and before loading complete
            widgetButtonWrapper.find('.add-widget-button').css('display', 'inline-block');
            var previous = widgetContainer.css('background-color');
            var animateFinish = function() {
                animateFinish = function() {
                    widgetContainer.css({backgroundColor: ''});
                };
                widgetContainer.animate({backgroundColor: previous}, animateFinish);
            };

            widgetContainer.animate({backgroundColor: '#F5F55B'}, 50, animateFinish);
        },

        /**
         * @param {Event} event
         * @private
         */
        _onClickAddWidget: function(event) {
            event.preventDefault();
            var columnIndex = $(event.target).closest('.dashboard-column').index();
            this.targetColumn = (columnIndex === -1) ? 0 : columnIndex;
            this.dialog.open();
        }
    };
});
