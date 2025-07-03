
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
            this.module.on('rendered', this.onRendered);

            this.module.on('focus', 'form input, form textarea, form select', this.formElementFocus);

            this.module.on('click', 'li.process summary:not(.collapsible-control)', this.processHeaderClick);
            this.module.on('end', 'ul.sortable', this.rowDropped);

            this.module.on('click', 'div.tiles > div', this.tileClick);
            this.module.on('click', '.dashboard-tile', this.dashboardTileClick);
            this.module.on('end', 'div.tiles.sortable', this.tileDropped);

            this.module.on('choose', '.sortable', this.suspendAutoRefresh);
            this.module.on('unchoose', '.sortable', this.resumeAutoRefresh);

            this.module.icinga.logger.debug('BP module initialized');
        },

        onRendered: function (event) {
            var $container = $(event.currentTarget);
            this.fixFullscreen($container);
            this.restoreCollapsedBps(event.target);
            this.highlightFormErrors($container);
            this.hideInactiveFormDescriptions($container);
            this.fixTileLinksOnDashboard($container);
        },

        // TODO: Remove once support for Icinga Web 2.10.x is dropped
        processHeaderClick: function (event) {
            event.stopPropagation();
            event.preventDefault();

            let details = event.currentTarget.parentNode;
            details.open = ! details.open;

            let bpUl = event.currentTarget.closest('.content > ul.bp');
            if (! bpUl || ! ('isRootConfig' in bpUl.dataset)) {
                return;
            }

            let bpName = bpUl.id;
            if (typeof this.idCache[bpName] === 'undefined') {
                this.idCache[bpName] = [];
            }

            let li = details.parentNode;
            let index = this.idCache[bpName].indexOf(li.id);
            if (! details.open) {
                if (index === -1) {
                    this.idCache[bpName].push(li.id);
                }
            } else if (index !== -1) {
                this.idCache[bpName].splice(index, 1);
            }
        },

        hideInactiveFormDescriptions: function($container) {
            $container.find('dd').not('.active').find('p.description').hide();
        },

        tileClick: function(event) {
            $(event.currentTarget).find('> a').first().trigger('click');
        },

        dashboardTileClick: function(event) {
            $(event.currentTarget).find('> .bp-link > a').first().trigger('click');
        },

        suspendAutoRefresh: function(event) {
            // TODO: If there is a better approach some time, let me know
            $(event.originalEvent.from).closest('.container').data('lastUpdate', (new Date()).getTime() + 3600 * 1000);
            event.stopPropagation();
        },

        resumeAutoRefresh: function(event) {
            var $container = $(event.originalEvent.from).closest('.container');
            $container.data('lastUpdate', (new Date()).getTime() - ($container.data('icingaRefresh') || 10) * 1000);
            event.stopPropagation();
        },

        tileDropped: function(event) {
            var evt = event.originalEvent;
            if (evt.oldIndex !== evt.newIndex) {
                var $source = $(evt.from);
                $source.addClass('progress')
                    .data('sortable').option('disabled', true);

                var data = {
                    csrfToken: $source.data('csrfToken'),
                    movenode: 'movenode', // That's the submit button..
                    parent: $(evt.to).data('nodeName') || '',
                    from: evt.oldIndex,
                    to: evt.newIndex
                };

                var actionUrl = [
                    $source.data('actionUrl'),
                    'action=move',
                    'movenode=' + $(evt.item).data('nodeName')
                ].join('&');

                var $container = $source.closest('.container');
                var icingaLoader = this.module.icinga.loader;
                icingaLoader.loadUrl(actionUrl, $container, data, 'POST')
                    .done((_, __, req) => icingaLoader.processNotificationHeader(req));
            }
        },

        rowDropped: function(event) {
            var evt = event.originalEvent,
                $source = $(evt.from),
                $target = $(evt.to);

            if (evt.oldIndex !== evt.newIndex || !$target.is($source)) {
                var $root = $target.closest('.content > ul.bp');
                $root.addClass('progress')
                    .find('ul.bp')
                    .add($root)
                    .each(function() {
                        $(this).data('sortable').option('disabled', true);
                    });

                var data = {
                    csrfToken: $target.data('csrfToken'),
                    movenode: 'movenode', // That's the submit button..
                    parent: $target.closest('.process').data('nodeName') || '',
                    from: evt.oldIndex,
                    to: evt.newIndex
                };

                var actionUrl = [
                    $source.data('actionUrl'),
                    'action=move',
                    'movenode=' + $(evt.item).data('nodeName')
                ].join('&');

                var $container = $target.closest('.container');
                var icingaLoader = this.module.icinga.loader;
                icingaLoader.loadUrl(actionUrl, $container, data, 'POST')
                    .done((_, __, req) => icingaLoader.processNotificationHeader(req));

                event.stopPropagation();
            }
        },

        /**
         * Called by Sortable.js while in Tree-View
         *
         * See group option on the sortable elements.
         *
         * @param to
         * @param from
         * @param item
         * @param event
         * @returns boolean
         */
        rowPutAllowed: function(to, from, item, event) {
            if (to.options.group.name === 'root') {
                return $(item).is('.process');
            }

            // Otherwise we're facing a nesting error next
            var $item = $(item),
                childrenNames = $item.find('.process').map(function () {
                    return $(this).data('nodeName');
                }).get();
            childrenNames.push($item.data('nodeName'));
            var loopDetected = $(to.el).parents('.process').toArray().some(function (parent) {
                return childrenNames.indexOf($(parent).data('nodeName')) !== -1;
            });

            return !loopDetected;
        },

        /**
         * Called by Sortable.js while in Tree-View
         *
         * See group option on the sortable elements.
         *
         * Currently only used when adding a new child using `Business Impact` action in the Host/Service Details view.
         *
         * @param to
         * @param from
         * @param item
         * @param event
         *
         * @returns boolean
         */
        rowPullAllowed: function(to, from, item, event) {
            return item.classList.contains('new-child');
        },

        fixTileLinksOnDashboard: function($container) {
            if ($container.closest('div.dashboard').length) {
                $container.find('div.tiles').data('baseTarget', '_next');
            }
        },

        fixFullscreen: function($container) {
            var $controls = $container.find('div.controls');
            var $layout = $('#layout');
            var icinga = this.module.icinga;
            if ($controls.hasClass('want-fullscreen')) {
                if (!$layout.hasClass('fullscreen-layout')) {
                    $layout.addClass('fullscreen-layout');
                    icinga.ui.currentLayout = 'fullscreen';
                }
            } else if (! $container.parent('.dashboard').length) {
                if ($layout.hasClass('fullscreen-layout')) {
                    $layout.removeClass('fullscreen-layout');
                    icinga.ui.layoutHasBeenChanged();
                }
            }
        },

        // TODO: Remove once support for Icinga Web 2.10.x is dropped
        restoreCollapsedBps: function(container) {
            let bpUl = container.querySelector('.content > ul.bp');
            if (! bpUl || ! ('isRootConfig' in bpUl.dataset)) {
                return;
            }

            let bpName = bpUl.id;
            if (typeof this.idCache[bpName] === 'undefined') {
                return;
            }

            bpUl.querySelectorAll('li.process').forEach(li => {
                if (this.idCache[bpName].indexOf(li.id) !== -1) {
                    li.querySelector(':scope > details').open = false;
                }
            });
        },

        /** BEGIN Form handling, borrowed from Director **/
        formElementFocus: function(ev)
        {
            var $input = $(ev.currentTarget);
            var $dd = $input.closest('dd');
            $dd.find('p.description').show();
            if ($dd.attr('id') && $dd.attr('id').match(/button/)) {
                return;
            }
            var $li = $input.closest('li');
            var $dt = $dd.prev();
            var $form = $dd.closest('form');

            $form.find('dt, dd, li').removeClass('active');
            $li.addClass('active');
            $dt.addClass('active');
            $dd.addClass('active');
            $dd.find('p.description.fading-out')
                .stop(true)
                .removeClass('fading-out')
                .fadeIn('fast');

            $form.find('dd').not($dd)
                .find('p.description')
                .not('.fading-out')
                .addClass('fading-out')
                .delay(2000)
                .fadeOut('slow', function() {
                    $(this).removeClass('fading-out').hide()
                });
        },

        highlightFormErrors: function($container)
        {
            $container.find('dd ul.errors').each(function(idx, ul) {
                var $ul = $(ul);
                var $dd = $ul.closest('dd');
                var $dt = $dd.prev();

                $dt.addClass('errors');
                $dd.addClass('errors');
            });
        }
        /** END Form handling **/
    };

    Icinga.availableModules.businessprocess = Bp;

}(Icinga));

