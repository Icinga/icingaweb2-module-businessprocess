<?php

namespace Icinga\Module\Businessprocess\ProvidedHook\Icingadb;

use Icinga\Module\Icingadb\Hook\ServiceActionsHook;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Web\Widget\Link;

class ServiceActions extends ServiceActionsHook
{
    public function getActionsForObject(Service $service): array
    {
        $label = mt('businessprocess', 'Business Impact');
        return array(
            new Link(
                $label,
                sprintf(
                    'businessprocess/node/impact?name=%s',
                    rawurlencode(
                        sprintf('%s;%s', $service->host->name, $service->name)
                    )
                )
            )
        );
    }
}
