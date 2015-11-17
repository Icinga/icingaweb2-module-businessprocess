
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

            this.module.on('click', 'table.bp.process > tbody > tr:first-child > td > a:last-child', this.processTitleClick);
            this.module.on('click', 'table.bp > tbody > tr:first-child > th', this.processOperatorClick);

            this.module.on('mouseenter', 'table.bp > tbody > tr > td > a', this.procMouseOver);
            this.module.on('mouseenter', 'table.bp > tbody > tr > th', this.procMouseOver);
            this.module.on('mouseenter', 'table.node.missing > tbody > tr > td > span', this.procMouseOver);
            this.module.on('mouseleave', 'div.bp', this.procMouseOut);

            this.module.icinga.logger.debug('BP module loaded');
        },

        processTitleClick: function (event) {
            event.stopPropagation();
            var $el = $(event.currentTarget).closest('table.bp');
            $el.toggleClass('collapsed');
        },

        processOperatorClick: function (event) {
            event.stopPropagation();
            var $el = $(event.currentTarget).closest('table.bp');

            // Click on arrow
            $el.removeClass('collapsed');

            var children = $el.find('> tbody > tr > td > table.bp.process');
            if (children.length === 0) {
                $el.toggleClass('collapsed');
                return;
            }
            if (children.filter('.collapsed').length) {
                children.removeClass('collapsed');
            } else {
                children.each(function(idx, el) {
                    var $el = $(el);
                    $el.addClass('collapsed');
                    $el.find('table.bp.process').addClass('collapsed');
                });
            }
        },

        /**
         * Add 'hovered' class to hovered title elements
         *
         * TODO: Skip on tablets
         */
        procMouseOver: function (event) {
            event.stopPropagation();
            var $hovered = $(event.currentTarget);
            var $el = $hovered.closest('table.bp');

            if ($el.is('.operator')) {
                if (!$hovered.closest('tr').is('tr:first-child')) {
                    // Skip hovered space between cols
                    return;
                }
            } else {
               // return;
            }

            $('table.bp.hovered').not($el.parents('table.bp')).removeClass('hovered'); // not self & parents
            $el.addClass('hovered');
            $el.parents('table.bp').addClass('hovered');
        },

        /**
         * Remove 'hovered' class from hovered title elements
         *
         * TODO: Skip on tablets
         */
        procMouseOut: function (event) {
            $('table.bp.hovered').removeClass('hovered');
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

    Icinga.availableModules.businessprocess = Bp;

}(Icinga));

