<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Application\Modules\Module;
use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Monitoring\Controller;
use Icinga\Module\Monitoring\DataView\DataView;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;

class ServiceController extends Controller
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
            $serviceName = $this->params->shift('service');

            $query = Service::on(IcingaDbObject::fetchDb());
            IcingaDbObject::applyIcingaDbRestrictions($query);

            $query->filter(Filter::all(
                Filter::equal('service.name', $serviceName),
                Filter::equal('host.name', $hostName)
            ));

            $service = $query->first();

            $this->params->add('name', $serviceName);
            $this->params->add('host.name', $hostName);

            if ($service !== null) {
                $this->redirectNow(Url::fromPath('icingadb/service')->setParams($this->params));
            }
        } else {
            $hostName = $this->params->get('host');
            $serviceName = $this->params->get('service');
            
            $query = $this->backend->select()
                ->from('servicestatus', array('service_description'))
                ->where('host_name', $hostName)
                ->where('service_description', $serviceName);

            $this->applyRestriction('monitoring/filter/objects', $query);
            if ($query->fetchRow() !== false) {
                $this->redirectNow(Url::fromPath('monitoring/service/show')->setParams($this->params));
            }
        }

        $this->view->host = $hostName;
        $this->view->service = $serviceName;
    }
}
