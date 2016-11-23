<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Web\Url;

class ServiceNode extends MonitoredNode
{
    protected $hostname;

    protected $service;

    protected $className = 'service';

    public function __construct(BusinessProcess $bp, $object)
    {
        $this->name = $object->hostname . ';' . $object->service;
        $this->hostname = $object->hostname;
        $this->service  = $object->service;
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
            return '<span class="missing">' . $view->escape($this->getAlias()) . '</span>';
        }

        $params = array(
            'host'    => $this->getHostname(),
            'service' => $this->getServiceDescription()
        );
        if ($this->bp->hasBackendName()) {
            $params['backend'] = $this->bp->getBackendName();
        }
        $link = $view->qlink($this->getAlias(), 'monitoring/service/show', $params);

        return $link;
    }

    protected function getActionIcons($view)
    {
        $icons = array();

        if (! $this->bp->isLocked()) {

            $url = Url::fromPath('businessprocess/node/simulate', array(
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

    public function getServiceDescription()
    {
        return $this->service;
    }

    public function getAlias()
    {
        return $this->hostname . ': ' . $this->service;
    }
}
