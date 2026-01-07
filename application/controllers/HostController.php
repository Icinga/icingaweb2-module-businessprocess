<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;
use ipl\Web\Compat\CompatController;

class HostController extends CompatController
{
    public function showAction(): void
    {
        $hostName = $this->params->shift('host');

        $query = Host::on(IcingaDbObject::fetchDb());
        IcingaDbObject::applyIcingaDbRestrictions($query);

        $query->filter(Filter::equal('host.name', $hostName));

        $host = $query->first();

        $this->params->add('name', $hostName);

        if ($host !== null) {
            $this->redirectNow(Url::fromPath('icingadb/host')->setParams($this->params));
        }

        $this->view->host = $hostName;
    }
}
