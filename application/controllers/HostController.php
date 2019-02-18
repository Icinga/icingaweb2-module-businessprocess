<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;

class HostController extends Controller
{
    public function showAction()
    {
        $host = $this->params->getRequired('host');

        $query = $this->backend->select()
            ->from('hoststatus', array('host_name'))
            ->where('host_name', $host);

        if ($this->applyRestriction('monitoring/filter/objects', $query)->fetchRow() !== false) {
            $this->redirectNow(Url::fromPath('monitoring/host/show')->setParams($this->params));
        }

        $this->view->host = $host;
    }
}
