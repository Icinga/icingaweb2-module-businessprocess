<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;

class HostController extends Controller
{
    protected $isIcingadb;

    protected $explicitIcingadb;

    protected function moduleInit()
    {
        $this->isIcingadb = $this->params->shift('backend') === '_icingadb';
        $this->explicitIcingadb = Module::exists('icingadb')
            && IcingadbSupport::useIcingaDbAsBackend();

        if (! $this->isIcingadb) {
            parent::moduleInit();
        }
    }

    public function showAction()
    {
        if ($this->isIcingadb || $this->explicitIcingadb) {
            $hostName = $this->params->shift('host');

            $query = Host::on(IcingaDbObject::fetchDb());
            IcingaDbObject::applyIcingaDbRestrictions($query);

            $query->filter(Filter::equal('host.name', $hostName));

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
