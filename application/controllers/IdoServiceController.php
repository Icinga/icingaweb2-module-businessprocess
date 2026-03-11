<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Monitoring\Controller;
use Icinga\Web\Url;

class IdoServiceController extends Controller
{
    public function showAction(): void
    {
        $hostName = $this->params->get('host');
        $serviceName = $this->params->get('service');

        $query = $this->backend->select()
            ->from('servicestatus', array('service_description'))
            ->where('host_name', $hostName)
            ->where('service_description', $serviceName);

        $this->applyRestriction('monitoring/filter/objects', $query);
        if ($query->fetchRow() !== false) {
            $this->redirectNow(Url::fromPath('monitoring/service/show')->setParams($this->params));
        } else {
            $this->view->host = $hostName;
            $this->view->service = $serviceName;
        }
    }
}
