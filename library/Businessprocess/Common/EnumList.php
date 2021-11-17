<?php

namespace Icinga\Module\Businessprocess\Common;

use Icinga\Data\Filter\Filter;
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

    protected function enumHostListByFilter($filter)
    {
        if ($this->useIcingaDbBackend()) {
            $names = (new IcingaDbBackend())->yieldHostnames($filter);
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

    protected function enumServiceListByFilter($filter)
    {
        $services = array();

        if ($this->useIcingaDbBackend()) {
            $objects = (new IcingaDbBackend())->fetchServices($filter);
            foreach ($objects as $object) {
                $services[$object->host->name . ';' . $object->name] = $object->host->name . ':' . $object->name;
            }
        } else {
            $objects = $this->backend
                ->select()
                ->from('serviceStatus', ['host' => 'host_name', 'service' => 'service_description'])
                ->applyFilter(Filter::fromQueryString($filter))
                ->applyFilter(MonitoringRestrictions::getRestriction('monitoring/filter/objects'))
                ->order('service_description')
                ->getQuery()
                ->fetchAll();
            foreach ($objects as $object) {
                $services[$object->host . ';' . $object->service] = $object->host . ':' . $object->service;
            }
        }

        return $services;
    }

    protected function useIcingaDbBackend()
    {
        return $this->backendName === '_icingadb';
    }
}
