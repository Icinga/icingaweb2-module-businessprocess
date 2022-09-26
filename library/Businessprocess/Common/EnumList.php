<?php

namespace Icinga\Module\Businessprocess\Common;

use Icinga\Application\Modules\Module;
use Icinga\Data\Filter\Filter;
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
        $suffix = ';Hoststatus';

        $object = (object) [
            'type' => 'host',
        ];

        foreach ($names as $name) {
            $object->id = $name . $suffix;
            $object->hostname = $name;
            $res[json_encode($object)] = $name;
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

        $object = (object) [
            'type' => 'service',
        ];

        $services = array();
        foreach ($names as $name) {
            $object->id = $host . ';' . $name;
            $object->hostname = $host;
            $object->servicename = $name;

            $services[json_encode($object)] = $name;
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
        $suffix = ';Hoststatus';
        $object = (object) [
            'type' => 'host',
        ];

        foreach ($names as $name) {
            $object->id = $name .$suffix;
            $object->hostname = $name;
            $res[json_encode($object)] = $name;
        }

        return $res;
    }

    protected function enumServiceListByFilter($filter)
    {
        $services = array();
        $object = (object) [
            'type' => 'service',
        ];

        if ($this->useIcingaDbBackend()) {
            $objects = (new IcingaDbObject())->fetchServices($filter);
            foreach ($objects as $obj) {
                $hostName = $obj->host->name;
                $serviceName = $obj->name;

                $object->id = StringQuoter::wrapString($hostName) . ';' . $serviceName;
                $object->hostname = $hostName;
                $object->servicename = $serviceName;

                $services[json_encode($object)] = $hostName . ':' . $serviceName;
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
            foreach ($objects as $obj) {
                $hostName = $obj->host;
                $serviceName = $obj->service;

                $object->id =$hostName . ';' . $serviceName;

                $object->hostname = $hostName;
                $object->servicename = $serviceName;

                $services[json_encode($object)] = $hostName . ':' . $serviceName;
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


    /**
     * Whether the given value is Host or Service
     *
     * @param $value
     *
     * @return bool
     */
    protected function isMonitoringNode($value)
    {
        if (StringQuoter::hasQuoteAtBeginning($value)) {
            $host = StringQuoter::stringBetweenQuotes($value);
            $value = substr($value, strlen($host) + 2); // 2 bcz two quotes
        }

        return  strpos($value, ';') !== false;
    }

    /**
     * Prepare host and service name
     *
     * @param $value
     *
     * @return array
     */
    protected function prepareMonitoringNode($value)
    {
        if (StringQuoter::hasQuoteAtBeginning($value)) { // check host
            $host = StringQuoter::stringBetweenQuotes($value);
            $service = substr($value, strlen($host) + 3); // 3 bcz two quotes and one semicolon

            $host = StringQuoter::undoQuoteEscaping($host);
        } else { //(strpos($value, ';') !== false)
            list($host, $service) = preg_split('~;~', $value, 2);
        }

        if (StringQuoter::hasQuoteAtBeginning($service)) { // check service
            $service = StringQuoter::undoQuoteEscaping(
                StringQuoter::stringBetweenQuotes($service)
            );
        }

        return [$host, $service];
    }

}
