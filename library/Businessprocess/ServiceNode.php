<?php

namespace Icinga\Module\Businessprocess;

class ServiceNode extends Node
{
    protected $hostname;
    protected $service;

    public function __construct(BusinessProcess $bp, $object)
    {
        $this->name = $object->hostname . ';' . $object->service;
        $this->hostname = $object->hostname;
        $this->service  = $object->service;
        $this->bp       = $bp;
        $this->setState($object->state);
    }

    public function renderLink($view)
    {
        if ($this->bp->isSimulationMode()) {
            return $view->qlink($this->getAlias(), 'businessprocess/node/simulate', array(
                'node' => $this->name,
                'processName' => $this->bp->getName()
            ));
        } else {
            return $view->qlink($this->getAlias(), 'monitoring/show/service', array(
                'host'    => $this->getHostname(),
                'service' => $this->getServiceDescription(),
                'processName' => $this->bp->getName()
            ));
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
}
