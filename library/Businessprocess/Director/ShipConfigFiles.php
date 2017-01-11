<?php

namespace Icinga\Module\Businessprocess\Director;

use Icinga\Application\Config;
use Icinga\Module\Director\Hook\ShipConfigFilesHook;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class ShipConfigFiles extends ShipConfigFilesHook
{
    public function fetchFiles()
    {
        $files = array();

        $storage = new LegacyStorage(
            Config::module('businessprocess')->getSection('global')
        );

        foreach ($storage->listProcesses() as $name => $title) {
            $files['processes/' . $name . '.bp'] = $storage->getSource($name);
        }

        return $files;
    }
}
