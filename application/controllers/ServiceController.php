<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Icingadb\Model\Service;
use Icinga\Web\Url;
use ipl\Html\HtmlString;
use ipl\Stdlib\Filter;
use ipl\Web\Compat\CompatController;

class ServiceController extends CompatController
{
    public function showAction(): void
    {
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

        $this->getTabs()->disableLegacyExtensions();

        $this->view->host = $hostName;
        $this->view->service = $serviceName;
        $this->view->tabs = null; // compatController already creates tabs
        $this->addContent(HtmlString::create($this->view->render('ido-service/show.phtml')));
    }
}
