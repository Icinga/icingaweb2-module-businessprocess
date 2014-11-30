<?php

namespace Icinga\Module\Bpapp;

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
            return $view->qlink($this->getAlias(), 'bpapp/node/simulate', array(
                'node' => $this->name
            ));
        } else {
            return $view->qlink($this->getAlias(), 'monitoring/show/service', array(
                'host'    => $this->getHostname(),
                'service' => $this->getServiceDescription()
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
