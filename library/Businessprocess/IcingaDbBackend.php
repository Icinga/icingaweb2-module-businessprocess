<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Orm\Compat\FilterProcessor;
use ipl\Orm\Query;

class IcingaDbBackend
{
    use MonitoringRestrictions;

    use IcingadbDatabase;

    /** @var BpConfig */
    protected $config;

    protected $backend;
    public function __construct()
    {
        $this->backend = $this->getDb();
    }

    public function fetchHosts()
    {

        $hosts = Host::on($this->getDb())
            ->orderBy('host.name');

        $this->applyMonitoringRestriction($hosts);

        return $hosts;
    }

    public function fetchServices($host)
    {
        $query = Service::on($this->backend)
            ->with('host');

        $query->getSelectBase()
            ->where(['service_host.name = ?' => $host])
            ->orderBy('service.name');

        $this->applyMonitoringRestriction($query);
        
        $queryServices = $query->assembleSelect();
        $services = $this->backend->select($queryServices)->fetchAll();
        var_dump($services);

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

    protected function applyMonitoringRestriction(Query $query)
    {
        FilterProcessor::apply(
            $this->getRestriction('monitoring/filter/objects'),
            $query
        );

        return $this;
    }
}
