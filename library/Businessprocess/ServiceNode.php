<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Web\Url;

class ServiceNode extends MonitoredNode
{
    protected $hostname;

    protected $service;

    protected $className = 'service';

    protected $icon = 'service';

    public function __construct($object)
    {
        $this->name = $object->hostname . ';' . $object->service;
        $this->hostname = $object->hostname;
        $this->service  = $object->service;
        if (isset($object->state)) {
            $this->setState($object->state);
        } else {
            $this->setState(0)->setMissing();
        }
    }

    public function getHostname()
    {
        return $this->hostname;
    }

    public function getServiceDescription()
    {
        return $this->service;
    }

    public function getAlias()
    {
        return $this->hostname . ': ' . $this->service;
    }

    public function getUrl()
    {
        $params = array(
            'host'    => $this->getHostname(),
            'service' => $this->getServiceDescription()
        );

        if ($this->getBpConfig()->hasBackendName()) {
            $params['backend'] = $this->getBpConfig()->getBackendName();
        }

        return Url::fromPath('businessprocess/service/show', $params);
    }
}
