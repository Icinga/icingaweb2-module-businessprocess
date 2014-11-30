<?php

namespace Icinga\Module\Bpapp;

class HostNode extends Node
{
    protected $hostname;

    public function __construct(BusinessProcess $bp, $object)
    {
        $this->name     = $object->hostname . ';Hoststate';
        $this->hostname = $object->hostname;
        $this->bp       = $bp;
        $this->setState($object->state);
    }

    public function renderLink($view)
    {
        if ($this->bp->isSimulationMode()) {
            return $view->qlink($this->getHostname(), 'bpapp/host/simulate', array(
                'node' => $this->name
            ));
        } else {
            return $view->qlink($this->getHostname(), 'monitoring/host/show', array(
                'host' => $this->getHostname
            ));
        }
    }

    public function getHostname()
    {
        return $this->hostname;
    }
}
