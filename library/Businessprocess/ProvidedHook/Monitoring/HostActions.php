<?php

namespace Icinga\Module\Businessprocess\ProvidedHook\Monitoring;

use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;

class HostActions extends HostActionsHook
{
    public function getActionsForHost(Host $host)
    {
        $label = mt('businessprocess', 'Business Impact');
        return array(
            $label => 'businessprocess/node/impact?name='
                . rawurlencode($host->getName() . ';Hoststatus')
        );
    }
}
