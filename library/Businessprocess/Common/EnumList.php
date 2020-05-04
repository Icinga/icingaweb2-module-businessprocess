<?php

namespace Icinga\Module\Businessprocess\Common;

use Icinga\Module\Businessprocess\IcingaDbBackend;
use Icinga\Module\Businessprocess\MonitoringRestrictions;

trait EnumList
{
    protected function enumHostForServiceList()
    {
        if ($this->useIcingaDbBackend()) {
            $names = (new IcingaDbBackend())->yieldHostnames();
        } else {
            $names = $this->backend
                ->select()
                ->from('hostStatus', ['hostname' => 'host_name'])
                ->applyFilter(MonitoringRestrictions::getRestriction('monitoring/filter/objects'))
                ->order('host_name')
                ->getQuery()
                ->fetchColumn();
        }

        // fetchPairs doesn't seem to work when using the same column with
        // different aliases twice
        $res = array();
        foreach ($names as $name) {
            $res[$name] = $name;
        }

        return $res;
    }

    protected function enumHostList()
    {
        if ($this->useIcingaDbBackend()) {
            $names = (new IcingaDbBackend())->yieldHostnames();
        } else {
            $names = $this->backend
                ->select()
                ->from('hostStatus', ['hostname' => 'host_name'])
                ->applyFilter(MonitoringRestrictions::getRestriction('monitoring/filter/objects'))
                ->order('host_name')
                ->getQuery()
                ->fetchColumn();
        }

        // fetchPairs doesn't seem to work when using the same column with
        // different aliases twice
        $res = array();
        $suffix = ';Hoststatus';
        foreach ($names as $name) {
            $res[$name . $suffix] = $name;
        }

        return $res;
    }

    protected function enumServiceList($host)
    {
        if ($this->useIcingaDbBackend()) {
            $names = (new IcingaDbBackend())->yieldServicenames($host);
        } else {
            $names = $this->backend
                ->select()
                ->from('serviceStatus', ['service' => 'service_description'])
                ->where('host_name', $host)
                ->applyFilter(MonitoringRestrictions::getRestriction('monitoring/filter/objects'))
                ->order('service_description')
                ->getQuery()
                ->fetchColumn();
        }

        $services = array();
        foreach ($names as $name) {
            $services[$host . ';' . $name] = $name;
        }

        return $services;
    }

    protected function useIcingaDbBackend()
    {
        return $this->backendName === '_icingadb';
    }
}
