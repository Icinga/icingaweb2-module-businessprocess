<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Web\Url;
use ipl\Html\HtmlString;
use ipl\Stdlib\Filter;
use ipl\Web\Compat\CompatController;

class HostController extends CompatController
{
    public function showAction(): void
    {
        $hostName = $this->params->shift('host');

        $query = Host::on(IcingaDbObject::fetchDb());
        IcingaDbObject::applyIcingaDbRestrictions($query);

        $query->filter(Filter::equal('host.name', $hostName));

        $host = $query->first();

        $this->params->add('name', $hostName);

        if ($host !== null) {
            $this->redirectNow(Url::fromPath('icingadb/host')->setParams($this->params));
        } else {
            $this->getTabs()->disableLegacyExtensions();

            $this->view->host = $hostName;
            $this->view->tabs = null; // compatController already creates tabs
            $this->addContent(HtmlString::create($this->view->render('ido-host/show.phtml')));
        }
    }
}
