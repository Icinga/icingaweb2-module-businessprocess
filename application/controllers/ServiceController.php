<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;

class ServiceController extends Controller
{
    public function showAction()
    {
        $host = $this->params->getRequired('host');
        $service = $this->params->getRequired('service');

        $query = $this->backend->select()
            ->from('servicestatus', array('service_description'))
            ->where('host_name', $host)
            ->where('service_description', $service);

        if ($this->applyRestriction('monitoring/filter/objects', $query)->fetchRow() !== false) {
            $this->redirectNow(Url::fromPath('monitoring/service/show')->setParams($this->params));
        }

        $this->view->host = $host;
        $this->view->service = $service;
    }
}
