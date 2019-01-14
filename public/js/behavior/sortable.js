/*! Icinga Web 2 | (c) 2018 Icinga Development Team | GPLv2+ */

(function(Icinga, $) {

    'use strict';

    Icinga.Behaviors = Icinga.Behaviors || {};

    var Sortable = function (icinga) {
        Icinga.EventListener.call(this, icinga);
        this.on('rendered', this.onRendered, this);
    };

    Sortable.prototype = new Icinga.EventListener();

    Sortable.prototype.onRendered = function(e) {
        $(e.target).find('.sortable').each(function() {
            var $el = $(this);
            var options = {
                onMove: function (/**Event*/ event, /**Event*/ originalEvent) {
                    if (typeof this.options['filter'] !== 'undefined' && $(event.related).is(this.options['filter'])) {
                        // Assumes the filtered item is either at the very start or end of the list and prevents the
                        // user from dropping other items before (if at the very start) or after it.
                        return false;
                    }
                }
            };

            $.each($el.data(), function (i, v) {
                if (i.length > 8 && i.startsWith('sortable')) {
                    options[i.charAt(8).toLowerCase() + i.substr(9)] = v;
                }
            });

            if (typeof options.group !== 'undefined' && typeof options.group.put === 'string' && options.group.put.startsWith('function:')) {
                var module = icinga.module($el.closest('.icinga-module').data('icingaModule'));
                options.group.put = module.object[options.group.put.substr(9)];
            }

            $(this).sortable(options);
        });
    };

    Icinga.Behaviors.Sortable = Sortable;

})(Icinga, jQuery);
