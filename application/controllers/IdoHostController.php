<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;

class IdoHostController extends Controller
{
    public function showAction(): void
    {
        $hostName = $this->params->get('host');

        $query = $this->backend->select()
            ->from('hoststatus', array('host_name'))
            ->where('host_name', $hostName);

        $this->applyRestriction('monitoring/filter/objects', $query);
        if ($query->fetchRow() !== false) {
            $this->redirectNow(Url::fromPath('monitoring/host/show')->setParams($this->params));
        }

        $this->view->host = $hostName;
    }
}
