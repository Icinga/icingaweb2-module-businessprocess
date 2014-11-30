
(function(Icinga) {

    var Bp = function(module) {
        /**
         * YES, we need Icinga
         */
        this.module = module;

        this.idCache = {};

        this.initialize();

        this.module.icinga.logger.debug('BP module loaded');
    };

    Bp.prototype = {

        initialize: function()
        {
            /**
             * Tell Icinga about our event handlers
             */
            this.module.on('beforerender', this.rememberOpenedBps);
            this.module.on('rendered',     this.fixOpenedBps);

            this.module.on('mouseenter', 'table.businessprocess th.bptitle', this.titleMouseOver);
            this.module.on('mouseleave', 'table.businessprocess th.bptitle', this.titleMouseOut);
            this.module.on('click', 'table.businessprocess th', this.titleClicked);

            this.module.icinga.logger.debug('BP module loaded');
        },

        /**
         * Add 'hovered' class to hovered title elements
         *
         * TODO: Skip on tablets
         */
        titleMouseOver: function (event) {
            event.stopPropagation();
            var el = $(event.currentTarget);
            el.addClass('hovered');
        },

        /**
         * Remove 'hovered' class from hovered title elements
         *
         * TODO: Skip on tablets
         */
        titleMouseOut: function (event) {
            event.stopPropagation();
            var el = $(event.currentTarget);
            el.removeClass('hovered');
        },

        /**
         * Handle clicks on operator or title element
         *
         * Title shows subelement, operator unfolds all subelements
         */
        titleClicked: function (event) {
            var self = this;
            event.stopPropagation();
            event.preventDefault();
            var $el = $(event.currentTarget),
                affected = []
                $container = $el.closest('.container');
            if ($el.hasClass('operator')) {
                $affected = $el.closest('table').children('tbody')
                    .children('tr.children').children('td').children('table');

                // Only if there are child BPs
                if ($affected.find('th.operator').length < 1) {
                    $affected = $el.closest('table');
                }
            } else {
                $affected = $el.closest('table');
            }
            $affected.each(function (key, el) {
                var $bptable = $(el).closest('table');
                $bptable.toggleClass('collapsed');
                if ($bptable.hasClass('collapsed')) {
                    $bptable.find('table').addClass('collapsed');
                }
            });

            /*$container.data('refreshParams', {
                opened: self.listOpenedBps($container)
            });*/
        },

        fixOpenedBps: function(event) {
            var $container = $(event.currentTarget);
            var container_id = $container.attr('id');

            if (typeof this.idCache[container_id] === 'undefined') {
                return;
            }
            var $procs = $('table.process', $container);
            $.each(this.idCache[$container.attr('id')], function(idx, id) {
                var $el = $('#' + id);
                $procs = $procs.not($el);

                $el.parents('table.process').each(function (idx, el) {
                    $procs = $procs.not($(el));

                });
            });

            $procs.addClass('collapsed');
        },

        /**
         * Get a list of all currently opened BPs.
         *
         * Only get the deepest nodes to keep requests as small as possible
         */
        rememberOpenedBps: function (event) {
            var $container = $(event.currentTarget);
            var ids = [];
            $('table.process', $container)
                .not('table.process.collapsed')
                .not('table.process.collapsed table.process')
                .each(function (key, el) {
                ids.push($(el).attr('id'));
            });
            if (ids.length === 0) {
                return;
            }

            this.idCache[$container.attr('id')] = ids;
        }
    };

    Icinga.availableModules.bpapp = Bp;

}(Icinga));

