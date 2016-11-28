<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Web\Url;

class HostNode extends MonitoredNode
{
    protected static $sortStateToStateMap = array(
        4 => self::ICINGA_DOWN,
        3 => self::ICINGA_UNREACHABLE,
        1 => self::ICINGA_PENDING,
        0 => self::ICINGA_UP
    );

    protected static $stateToSortStateMap = array(
        self::ICINGA_PENDING     => 1,
        self::ICINGA_UNREACHABLE => 3,
        self::ICINGA_DOWN        => 4,
        self::ICINGA_UP          => 0,
    );

    protected static $state_names = array(
        'UP',
        'DOWN',
        'UNREACHABLE',
        99 => 'PENDING'
    );

    protected $hostname;

    protected $className = 'host';

    public function __construct(BusinessProcess $bp, $object)
    {
        $this->name     = $object->hostname . ';Hoststatus';
        $this->hostname = $object->hostname;
        $this->bp       = $bp;
        if (isset($object->state)) {
            $this->setState($object->state);
        } else {
            $this->setState(0)->setMissing();
        }
    }

    public function getAlias()
    {
        return $this->getHostname();
    }

    public function getHostname()
    {
        return $this->hostname;
    }

    public function getUrl()
    {
        $params = array(
            'host' => $this->getHostname(),
        );

        if ($this->bp->hasBackendName()) {
            $params['backend'] = $this->bp->getBackendName();
        }

        return Url::fromPath('monitoring/host/show', $params);
    }

    public function getLink()
    {
        return Link::create($this->hostname, $this->getUrl());
    }
}
