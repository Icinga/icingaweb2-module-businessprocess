<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Web\Component\Link;
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

    public function renderLink($view)
    {
        if ($this->isMissing()) {
            return '<span class="missing">' . $view->escape($this->hostname) . '</span>';
        }

        $params = array(
            'host'    => $this->getHostname(),
        );

        if ($this->bp->hasBackendName()) {
            $params['backend'] = $this->bp->getBackendName();
        }
        return Link::create($this->hostname, 'monitoring/host/show', $params)->render();
    }

    protected function getActionIcons($view)
    {
        $icons = array();

        if (! $this->bp->isLocked()) {

            $url = Url::fromPath( 'businessprocess/node/simulate', array(
                'config' => $this->bp->getName(),
                'node' => $this->name
            ));

            $icons[] = $this->actionIcon(
                $view,
                'magic',
                $url,
                'Simulation'
            );
        }

        return $icons;
    }

    public function getHostname()
    {
        return $this->hostname;
    }
}
