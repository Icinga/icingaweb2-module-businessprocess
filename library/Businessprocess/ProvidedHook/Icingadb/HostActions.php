<?php

namespace Icinga\Module\Businessprocess\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\HostActionsHook;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Web\Widget\Link;

class HostActions extends HostActionsHook
{
    public function getActionsForObject(Host $host): array
    {
        $label = mt('businessprocess', 'Business Impact');
        return array(
            new Link(
                $label,
                'businessprocess/node/impact?name='
                . rawurlencode($host->name . ';Hoststatus')
            )
        );
    }
}
