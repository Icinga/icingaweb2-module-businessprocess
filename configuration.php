<?php

use Icinga\Module\Businessprocess\Storage\LegacyStorage;

$this->providePermission('businessprocess/create', 'Allow to create new configs');
$this->providePermission('businessprocess/modify', 'Allow to modify processes');
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
    foreach ($storage->listProcesses() as $name) {
        $prio++;

        $meta = $storage->loadMetadata($name);
        if ($meta->get('AddToMenu') === 'no') {
            continue;
        }

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
