<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Monitoring\Backend;
use Icinga\Web\Url;

class HostController extends Controller
{
    use IcingadbDatabase;

    protected $backend;

    protected $allParams;


    public function showAction()
    {
        $this->allParams = $this->getAllParams();

        $host = $this->params->getRequired('host');

        if (array_key_exists('backend', $this->allParams)) {
            if ($this->allParams['backend'] === '_icingadb') {
                $this->backend = $this->getDb();
            }
            $query = Host::on($this->backend);
            $query->getSelectBase()
                ->where(['host.name = ?' => $host]);
            $this->applyMonitoringRestriction($query);

            $queryHost = $query->columns('host.name')->assembleSelect();
            $queryHost = $this->backend->select($queryHost)->fetch();

            $this->params->add('name', $host);

            if ($queryHost !== false) {
                $this->redirectNow(Url::fromPath('icingadb/host/index')->setParams($this->params));
            }

        } else {
            $this->backend = Backend::createBackend($this->_getParam('backend'));
            $query = $this->backend->select()
                ->from('hoststatus', array('host_name'))
                ->where('host_name', $host);

            if ($this->applyRestriction('monitoring/filter/objects', $query)->fetchRow() !== false) {
                $this->redirectNow(Url::fromPath('monitoring/host/show')->setParams($this->params));
            }
        }

        $this->view->host = $host;
    }
}
