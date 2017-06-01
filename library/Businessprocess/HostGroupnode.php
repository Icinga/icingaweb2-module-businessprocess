<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Web\Url;

class HostGroupnode extends MonitoredNode
{
    protected $className = 'hostgroup';

    protected $stateSummary = true;

    protected $hostgroup_name;

    public function __construct(BpConfig $bp, $object)
    {
        $this->name = 'HOSTGROUP;' . $object->name;
        $this->hostgroup_name = $object->name;
        $this->bp = $bp;
        if (isset($object->state)) {
            $this->setState($object->state);
        } else {
            $this->setState(3)->setMissing();
            $this->counters = static::$emptyStateSummary;
            $this->counters['MISSING'] = 1;
        }
    }

    public function getAlias()
    {
        return $this->getHostgroupName();
    }

    public function getHostgroupName()
    {
        return $this->hostgroup_name;
    }

    public function getUrl()
    {
        $params = array(
            'hostgroup' => $this->getHostgroupName(),
            'sort'      => 'service_severity',
            'dir'       => 'desc',
        );

        if ($this->bp->hasBackendName()) {
            $params['backend'] = $this->bp->getBackendName();
        }

        return Url::fromPath('monitoring/list/services', $params);
    }

    public function setCounters($counters)
    {
        $this->counters = $counters;
        return $this;
    }
}
