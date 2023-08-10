<?php

namespace Icinga\Module\Businessprocess\Monitoring\DataView;

use Icinga\Data\ConnectionInterface;
use Icinga\Module\Businessprocess\Monitoring\Backend\Ido\Query\ServiceStatusQuery;

class ServiceStatus extends \Icinga\Module\Monitoring\DataView\Servicestatus
{
    public function __construct(ConnectionInterface $connection, array $columns = null)
    {
        parent::__construct($connection, $columns);

        $this->query = new ServiceStatusQuery($connection->getResource(), $columns);
    }
}
