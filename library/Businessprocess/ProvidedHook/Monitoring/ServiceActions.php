<?php

namespace Icinga\Module\Businessprocess\ProvidedHook\Monitoring;

use Exception;
use Icinga\Application\Config;
use Icinga\Module\Monitoring\Hook\ServiceActionsHook;
use Icinga\Module\Monitoring\Object\Service;
use Icinga\Web\Url;

class ServiceActions extends ServiceActionsHook
{
    public function getActionsForService(Service $service)
    {
        $label = mt('businessprocess', 'Business Impact');
        return array(
            $label => sprintf(
                'businessprocess/node/impact?name=%s',
                rawurlencode(
                    sprintf('%s;%s', $service->getHost()->getName(), $service->getName())
                )
            )
        );
    }
}
