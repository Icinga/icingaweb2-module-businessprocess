<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Web\Url;

class ServiceNode extends MonitoredNode
{
    protected $hostname;

    /** @var string Alias of the host */
    protected $hostAlias;

    protected $service;

    protected $className = 'service';

    protected $icon = 'service';

    public function __construct($object)
    {
        if (isset($object->statesOverride)) {
            $this->name = $object->hostname . ';' . $object->service . ':' . $object->statesOverride;
            $this->setStatesOverride($object->statesOverride);
        } else {
            $this->name = $object->hostname . ';' . $object->service;
        }
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

    /**
     * Get the host alias
     *
     * @return string
     */
    public function getHostAlias()
    {
        return $this->hostAlias;
    }

    /**
     * Set the host alias
     *
     * @param string $hostAlias
     *
     * @return $this
     */
    public function setHostAlias($hostAlias)
    {
        $this->hostAlias = $hostAlias;

        return $this;
    }

    public function getServiceDescription()
    {
        return $this->service;
    }

    public function getAlias()
    {
        return $this->getHostAlias() . ': ' . $this->alias;
    }
    
    public function getShortName()
    {
        return $this->hostname . ';' . $this->service;
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
