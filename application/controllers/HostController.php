<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\IcingaDbBackend;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;

class HostController extends Controller
{
    use IcingadbDatabase;

    public function showAction()
    {
        $icingadb = $this->params->shift('icingadb');

        if ($icingadb) {
            $hostName = $this->params->shift('host');

            $query = Host::on($this->getDb());
            IcingaDbBackend::applyIcingaDbRestrictions($query);

            $query->getSelectBase()
                ->where(['host.name = ?' => $hostName]);

            $host = $query->first();

            $this->params->add('name', $hostName);

            if ($host !== false) {
                $this->redirectNow(Url::fromPath('icingadb/host')->setParams($this->params));
            }
        } else {
            $hostName = $this->params->get('host');

            $query = $this->backend->select()
                ->from('hoststatus', array('host_name'))
                ->where('host_name', $hostName);

            if ($this->applyRestriction('monitoring/filter/objects', $query)->fetchRow() !== false) {
                $this->redirectNow(Url::fromPath('monitoring/host/show')->setParams($this->params));
            }
        }

        $this->view->host = $hostName;
    }
}
