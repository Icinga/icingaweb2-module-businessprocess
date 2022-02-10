<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Dompdf\Exception;
use Icinga\Application\Modules\Module;
use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;
use ipl\Stdlib\Filter;

class ServiceController extends Controller
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
        $icingadb = $this->params->shift('icingadb');

        if ($icingadb && Module::exists('icingadb')) {
            $hostName = $this->params->shift('host');
            $serviceName = $this->params->shift('service');

            $query = Service::on(IcingaDbObject::fetchDb())->with('host');
            IcingaDbObject::applyIcingaDbRestrictions($query);

            $query->filter(Filter::all(
                Filter::equal('service.name', $serviceName),
                Filter::equal('host.name', $hostName)
            ));

            $service = $query->first();

            $this->params->add('name', $serviceName);
            $this->params->add('host.name', $hostName);

            if ($service !== false) {
                $this->redirectNow(Url::fromPath('icingadb/service')->setParams($this->params));
            }
        } else {
            $hostName = $this->params->get('host');
            $serviceName = $this->params->get('service');
            
            $query = $this->backend->select()
                ->from('servicestatus', array('service_description'))
                ->where('host_name', $hostName)
                ->where('service_description', $serviceName);

            if ($this->applyRestriction('monitoring/filter/objects', $query)->fetchRow() !== false) {
                $this->redirectNow(Url::fromPath('monitoring/service/show')->setParams($this->params));
            }
        }

        $this->view->host = $hostName;
        $this->view->service = $serviceName;
    }
}
