<?php

namespace Icinga\Module\Businessprocess\Common;

use Icinga\Application\Modules\Module;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Businessprocess\MonitoringRestrictions;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;

trait EnumList
{
    protected function enumHostForServiceList()
    {
        if ($this->useIcingaDbBackend()) {
            $names = (new IcingaDbObject())->yieldHostnames();
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
            $names = (new IcingaDbObject())->yieldHostnames();
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
            $res[BpConfig::joinNodeName($name, 'Hoststatus')] = $name;
        }

        return $res;
    }

    protected function enumServiceList($host)
    {
        if ($this->useIcingaDbBackend()) {
            $names = (new IcingaDbObject())->yieldServicenames($host);
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
            $services[BpConfig::joinNodeName($host, $name)] = $name;
        }

        return $services;
    }

    protected function enumHostListByFilter($filter)
    {
        if ($this->useIcingaDbBackend()) {
            $names = (new IcingaDbObject())->yieldHostnames($filter);
        } else {
            $names = $this->backend
                ->select()
                ->from('hostStatus', ['hostname' => 'host_name'])
                ->applyFilter(Filter::fromQueryString($filter))
                ->applyFilter(MonitoringRestrictions::getRestriction('monitoring/filter/objects'))
                ->order('host_name')
                ->getQuery()
                ->fetchColumn();
        }

        // fetchPairs doesn't seem to work when using the same column with
        // different aliases twice
        $res = array();
        foreach ($names as $name) {
            $res[BpConfig::joinNodeName($name, 'Hoststatus')] = $name;
        }

        return $res;
    }

    protected function enumServiceListByFilter($filter)
    {
        $services = array();

        if ($this->useIcingaDbBackend()) {
            $objects = (new IcingaDbObject())->fetchServices($filter);
            foreach ($objects as $object) {
                $services[BpConfig::joinNodeName($object->host->name, $object->name)]
                    = $object->host->name . ':' . $object->name;
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
                $services[BpConfig::joinNodeName($object->host, $object->service)]
                    = $object->host . ':' . $object->service;
            }
        }

        return $services;
    }

    protected function enumHostStateList()
    {
        $hostStateList = [
            0 => $this->translate('UP'),
            1 => $this->translate('DOWN'),
            99 => $this->translate('PENDING')
        ];

        return $hostStateList;
    }

    protected function enumServiceStateList()
    {
        $serviceStateList = [
            0 => $this->translate('OK'),
            1 => $this->translate('WARNING'),
            2 => $this->translate('CRITICAL'),
            3 => $this->translate('UNKNOWN'),
            99 => $this->translate('PENDING'),
        ];

        return $serviceStateList;
    }

    protected function useIcingaDbBackend()
    {
        if (Module::exists('icingadb')) {
            return ! $this->bp->hasBackendName() && IcingadbSupport::useIcingaDbAsBackend();
        }

        return false;
    }
}
