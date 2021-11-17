<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Web\Filter\QueryString;

class IcingaDbObject
{
    use IcingadbDatabase;

    use Auth;

    /** @var BpConfig */
    protected $config;

    /** @var IcingadbDatabase */
    protected $conn;

    public function __construct()
    {
        $this->conn = $this->getDb();
    }

    public function fetchHosts($filter = null)
    {

        $hosts = Host::on($this->conn);

        if ($filter !== null) {
            $filterQuery = QueryString::parse($filter);

            $hosts->filter($filterQuery);
        }

        $hosts->orderBy('host.name');

        $this->applyIcingaDbRestrictions($hosts);

        return $hosts;
    }

    public function fetchServices($filter)
    {
        $services = Service::on($this->conn)
            ->with('host');

        if ($filter !== null) {
            $filterQuery = QueryString::parse($filter);

            $services->filter($filterQuery);
        }

        $services->orderBy('service.name');

        $this->applyIcingaDbRestrictions($services);

        return $services;
    }

    public function yieldHostnames($filter = null)
    {
        foreach ($this->fetchHosts($filter) as $host) {
            yield $host->name;
        }
    }

    public function yieldServicenames($host)
    {
        $filter = "host.name=$host";

        foreach ($this->fetchServices($filter) as $service) {
            yield $service->name;
        }
    }

    public static function applyIcingaDbRestrictions($query)
    {
        $object = new self;
        $object->applyRestrictions($query);

        return $object;
    }
}
