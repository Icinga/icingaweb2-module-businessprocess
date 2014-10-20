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

    public function getHostname()
    {
        return $this->hostname;
    }
}
