
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

            this.module.on('click', 'li.process a.toggle', this.processToggleClick);
            this.module.on('click', 'li.process > div', this.processHeaderClick);
            this.module.on('end', 'ul.sortable', this.rowDropped);

            this.module.on('click', 'div.tiles > div', this.tileClick);
            this.module.on('click', '.dashboard-tile', this.dashboardTileClick);
            this.module.on('end', 'div.tiles.sortable', this.tileDropped);

            this.module.icinga.logger.debug('BP module initialized');
        },

        onRendered: function (event) {
            var $container = $(event.currentTarget);
            this.fixFullscreen($container);
            this.restoreCollapsedBps($container);
            this.highlightFormErrors($container);
            this.hideInactiveFormDescriptions($container);
            this.fixTileLinksOnDashboard($container);
        },

        processToggleClick: function (event) {
            event.stopPropagation();

            var $li = $(event.currentTarget).closest('li.process');
            $li.toggleClass('collapsed');

            var $bpUl = $(event.currentTarget).closest('.content > ul.bp');
            if (! $bpUl.length || !$bpUl.data('isRootConfig')) {
                return;
            }

            var bpName = $bpUl.attr('id');
            if (typeof this.idCache[bpName] === 'undefined') {
                this.idCache[bpName] = [];
            }

            var index = this.idCache[bpName].indexOf($li.attr('id'));
            if ($li.is('.collapsed')) {
                if (index === -1) {
                    this.idCache[bpName].push($li.attr('id'));
                }
            } else if (index !== -1) {
                this.idCache[bpName].splice(index, 1);
            }
        },

        processHeaderClick: function (event) {
            this.processToggleClick(event);
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

        tileDropped: function(event) {
            var evt = event.originalEvent;
            if (evt.oldIndex !== evt.newIndex) {
                var $source = $(evt.from);
                var actionUrl = icinga.utils.addUrlParams($source.data('actionUrl'), {
                    action: 'move',
                    movenode: $(evt.item).data('nodeName')
                });

                if (! $source.is('.few') && $('.addnew', $source).length === 2) {
                    // This assumes we're not moving things between different lists
                    evt.oldIndex -= 1;
                    evt.newIndex -= 1;
                }

                var data = {
                    csrfToken: $source.data('csrfToken'),
                    movenode: 'movenode', // That's the submit button..
                    parent: $(evt.to).data('nodeName') || '',
                    from: evt.oldIndex,
                    to: evt.newIndex
                };

                icinga.loader.loadUrl(actionUrl, $source.closest('.container'), data, 'POST');
            }
        },

        rowDropped: function(event) {
            var evt = event.originalEvent,
                $source = $(evt.from),
                $target = $(evt.to);

            if (evt.oldIndex !== evt.newIndex || !$target.is($source)) {
                var $root = $target.closest('.content > ul.bp');
                $root.addClass('progress');

                var data = {
                    csrfToken: $target.data('csrfToken'),
                    movenode: 'movenode', // That's the submit button..
                    parent: $target.parent('.process').data('nodeName') || '',
                    from: evt.oldIndex,
                    to: evt.newIndex
                };

                var actionUrl = icinga.utils.addUrlParams($source.data('actionUrl'), {
                    action: 'move',
                    movenode: $(evt.item).data('nodeName')
                });

                icinga.loader.loadUrl(actionUrl, $target.closest('.container'), data, 'POST');
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
                    $controls.removeAttr('style');
                    $container.find('.fake-controls').remove();
                    icinga.ui.currentLayout = 'fullscreen';
                }
            } else if (! $container.parent('.dashboard').length) {
                if ($layout.hasClass('fullscreen-layout')) {
                    $layout.removeClass('fullscreen-layout');
                    icinga.ui.layoutHasBeenChanged();
                    icinga.ui.initializeControls($container);
                }
            }
        },

        restoreCollapsedBps: function($container) {
            var $bpUl = $container.find('.content > ul.bp');
            if (! $bpUl.length || !$bpUl.data('isRootConfig')) {
                return;
            }

            var bpName = $bpUl.attr('id');
            if (typeof this.idCache[bpName] === 'undefined') {
                return;
            }

            var _this = this;
            $bpUl.find('li.process')
                .filter(function () {
                    return _this.idCache[bpName].indexOf(this.id) !== -1;
                })
                .addClass('collapsed');
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

