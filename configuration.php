<?php

use Icinga\Module\Businessprocess\Storage\LegacyStorage;

/** @var \Icinga\Application\Modules\Module $this */
$section = $this->menuSection(N_('Business Processes'), array(
    'url'      => 'businessprocess',
    'icon'     => 'sitemap',
    'priority' => 46
));

try {
    $storage = new LegacyStorage(
        $this->getConfig()->getSection('global')
    );

    $prio = 0;
    foreach ($storage->listProcessNames() as $name) {
        $meta = $storage->loadMetadata($name);
        if ($meta->get('AddToMenu') === 'no') {
            continue;
        }
        $prio++;

        if ($prio > 5) {
            $section->add(N_('Show all'), array(
                'url' => 'businessprocess',
                'priority' => $prio
            ));

            break;
        }

        $section->add($meta->getTitle(), array(
            'url' => 'businessprocess/process/show',
            'urlParameters' => array('config' => $name),
            'priority' => $prio
        ));
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

$this->provideJsFile('behavior/sortable.js');
$this->provideJsFile('vendor/jquery.fn.sortable.js');