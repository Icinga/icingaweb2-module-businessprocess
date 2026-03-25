<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\Forms\GeneralConfigForm;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Web\Navigation\Renderer\ProcessProblemsBadge;

/** @var \Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Business Processes'), array(
    'renderer' => 'ProcessesProblemsBadge',
    'url'      => 'businessprocess',
    'icon'     => 'sitemap',
    'priority' => 46
));

try {
    $storage = LegacyStorage::getInstance();
    $maxMenuProcesses = Config::module('businessprocess')
        ->getSection('general')
        ->get('max_menu_processes', GeneralConfigForm::MAX_MENU_PROCESSES);

    if ($maxMenuProcesses > 0) {
        $prio = 0;
        foreach ($storage->listProcessNames() as $name) {
            $meta = $storage->loadMetadata($name);
            if ($meta->get('AddToMenu') === 'no') {
                continue;
            }
            $prio++;

            if ($prio > $maxMenuProcesses) {
                $section->add(N_('Show all'), array(
                    'url' => 'businessprocess',
                    'priority' => $prio
                ));

                break;
            }

            $section->add($meta->getTitle(), array(
                'renderer' => (new ProcessProblemsBadge())->setBpConfigName($name),
                'url' => 'businessprocess/process/show',
                'urlParameters' => array('config' => $name),
                'priority' => $prio
            ));
        }
    }
} catch (Exception $e) {
    // Well... there is not much we could do here
}

$this->providePermission(
    'businessprocess/showall',
    $this->translate('Allow to see all available processes, regardless of configured restrictions')
);
$this->providePermission(
    'businessprocess/create',
    $this->translate('Allow to create whole new process configuration (files)')
);
$this->providePermission(
    'businessprocess/modify',
    $this->translate('Allow to modify process definitions, to add and remove nodes')
);
$this->provideRestriction(
    'businessprocess/prefix',
    $this->translate('Restrict access to configurations with the given prefix')
);

$this->provideConfigTab(
    'general',
    [
        'title' => $this->translate('General'),
        'label' => $this->translate('General'),
        'url'   => 'config/general'
    ]
);

$this->provideJsFile('vendor/Sortable.js');
$this->provideJsFile('behavior/sortable.js');
$this->provideJsFile('vendor/jquery.fn.sortable.js');
