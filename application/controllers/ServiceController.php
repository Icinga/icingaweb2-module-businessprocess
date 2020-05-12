<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\IcingaDbBackend;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;

class ServiceController extends Controller
{
    use IcingadbDatabase;

    public function showAction()
    {
        $icingadb = $this->params->shift('icingadb');

        if ($icingadb) {
            $hostName = $this->params->shift('host');
            $serviceName = $this->params->shift('service');

            $service = Service::on($this->getDb())->with('host');
            $service->getSelectBase()
                ->where(['service_host.name = ?' => $hostName, 'service.name = ?' => $serviceName]);

            IcingaDbBackend::applyMonitoringRestriction($service);

            $rs = $service->columns('host.name')->first();

            $this->params->add('name', $serviceName);
            $this->params->add('host.name', $hostName);

            if ($rs !== false) {
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
