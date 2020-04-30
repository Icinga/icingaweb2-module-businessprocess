<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Web\Url;
use ipl\Html\Html;

class HostNode extends MonitoredNode
{
    protected $sortStateToStateMap = array(
        4 => self::ICINGA_DOWN,
        3 => self::ICINGA_UNREACHABLE,
        1 => self::ICINGA_PENDING,
        0 => self::ICINGA_UP
    );

    protected $stateToSortStateMap = array(
        self::ICINGA_PENDING     => 1,
        self::ICINGA_UNREACHABLE => 3,
        self::ICINGA_DOWN        => 4,
        self::ICINGA_UP          => 0,
    );

    protected $stateNames = array(
        'UP',
        'DOWN',
        'UNREACHABLE',
        99 => 'PENDING'
    );

    protected $hostname;

    protected $className = 'host';

    protected $icon = 'host';

    public function __construct($object)
    {
        $this->name     = $object->hostname . ';Hoststatus';
        $this->hostname = $object->hostname;
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

    public function getUrl()
    {
        $params = array(
            'host' => $this->getHostname(),
        );

        if ($this->getBpConfig()->hasBackendName()) {
            $backendName = $this->getBpConfig()->getBackendName();
            if ($backendName === '_icingadb') {
                $params['icingadb'] = 1;
            } else {
                $params['backend'] = $this->getBpConfig()->getBackendName();
            }
        }

        return Url::fromPath('businessprocess/host/show', $params);
    }
}
