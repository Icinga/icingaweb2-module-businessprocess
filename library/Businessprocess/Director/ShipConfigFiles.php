<?php

namespace Icinga\Module\Businessprocess\Director;

use Icinga\Module\Director\Hook\ShipConfigFilesHook;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class ShipConfigFiles extends ShipConfigFilesHook
{
    public function fetchFiles()
    {
        $files = array();

        $storage = LegacyStorage::getInstance();

        foreach ($storage->listProcesses() as $name => $title) {
            $files['processes/' . $name . '.bp'] = $storage->getSource($name);
        }

        return $files;
    }
}
