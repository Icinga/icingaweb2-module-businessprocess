<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Module\Monitoring\Backend;
use Icinga\Web\Url;

class ServiceController extends Controller
{
    use IcingadbDatabase;

    protected $backend;

    protected $allParams;

    public function showAction()
    {
        $this->allParams = $this->getAllParams();

        $host = $this->params->getRequired('host');
        $service = $this->params->getRequired('service');

        if (array_key_exists('backend', $this->allParams)) {
            if ($this->allParams['backend'] === '_icingadb') {
                $this->backend = $this->getDb();
            }
            $query = Service::on($this->backend)->with('host');
            $query->getSelectBase()
                ->where(['service_host.name = ?' => $host, 'service.name = ?' => $service]);
            $this->applyMonitoringRestriction($query);

            $query = $query->columns('host.name')->assembleSelect();
            $query = $this->backend->select($query)->fetch();

            $this->params->add('name', $service);
            $this->params->add('host.name', $host);

            if ($query !== false) {
                $this->redirectNow(Url::fromPath('icingadb/service/index')->setParams($this->params));
            }

        } else {
            $this->backend = Backend::createBackend($this->_getParam('backend'));
            $query = $this->backend->select()
                ->from('servicestatus', array('service_description'))
                ->where('host_name', $host)
                ->where('service_description', $service);

            if ($this->applyRestriction('monitoring/filter/objects', $query)->fetchRow() !== false) {
                $this->redirectNow(Url::fromPath('monitoring/service/show')->setParams($this->params));
            }
        }

        $this->view->host = $host;
        $this->view->service = $service;
    }
}
