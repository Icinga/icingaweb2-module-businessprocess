<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\DataView\DataView;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;

class HostController extends Controller
{
    /**
     * True if business process prefers to use icingadb as backend for it's nodes
     *
     * @var bool
     */
    protected $isIcingadbPreferred;

    protected function moduleInit()
    {
        $this->isIcingadbPreferred = Module::exists('icingadb')
            && ! $this->params->has('backend')
            && IcingadbSupport::useIcingaDbAsBackend();

        if (! $this->isIcingadbPreferred) {
            parent::moduleInit();
        }
    }

    public function showAction()
    {
        if ($this->isIcingadbPreferred) {
            $hostName = $this->params->shift('host');

            $query = Host::on(IcingaDbObject::fetchDb());
            IcingaDbObject::applyIcingaDbRestrictions($query);

            $query->filter(Filter::equal('host.name', $hostName));

            $host = $query->first();

            $this->params->add('name', $hostName);

            if ($host !== null) {
                $this->redirectNow(Url::fromPath('icingadb/host')->setParams($this->params));
            }
        } else {
            $hostName = $this->params->get('host');

            $query = $this->backend->select()
                ->from('hoststatus', array('host_name'))
                ->where('host_name', $hostName);

            /** @var DataView $restrictedQuery */
            $restrictedQuery = $this->applyRestriction('monitoring/filter/objects', $query);
            if ($restrictedQuery->fetchRow() !== false) {
                $this->redirectNow(Url::fromPath('monitoring/host/show')->setParams($this->params));
            }
        }

        $this->view->host = $hostName;
    }
}
