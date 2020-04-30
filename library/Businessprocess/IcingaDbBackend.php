<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use ipl\Orm\Compat\FilterProcessor;
use ipl\Orm\Query;

class IcingaDbBackend
{
    use IcingadbDatabase;

    /** @var BpConfig */
    protected $config;

    /** @var IcingadbDatabase */
    protected $conn;

    public function __construct()
    {
        $this->conn = $this->getDb();
    }

    public function fetchHosts()
    {
        $hosts = Host::on($this->conn)
            ->orderBy('host.name');

        $this->applyMonitoringRestriction($hosts);

        return $hosts;
    }

    public function fetchServices($host)
    {
        $services = Service::on($this->conn)
            ->with('host');

        $services->getSelectBase()
            ->where(['service_host.name = ?' => $host])
            ->orderBy('service.name');

        $this->applyMonitoringRestriction($services);

        return $services;
    }

    public function yieldHostnames()
    {
        foreach ($this->fetchHosts() as $host) {
            yield $host->name;
        }
    }

    public function yieldServicenames($host)
    {
        foreach ($this->fetchServices($host) as $service) {
            yield $service->name;
        }
    }

    public static function applyMonitoringRestriction(Query $query)
    {
        $restriction = FilterProcessor::apply(
            MonitoringRestrictions::getRestriction('monitoring/filter/objects'),
            $query
        );

        return $restriction;
    }
}
