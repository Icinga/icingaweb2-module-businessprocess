<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Notification;
use Icinga\Web\Session\SessionNamespace;

class ProcessForm extends QuickForm
{
    /** @var MonitoringBackend */
    protected $backend;

    /** @var BusinessProcess */
    protected $bp;

    /** @var BpNode */
    protected $node;

    protected $objectList = array();

    protected $processList = array();

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        $this->addElement('text', 'name', array(
            'label'        => $this->translate('Name'),
            'required'     => true,
            'description' => $this->translate(
                'This is the unique identifier of this process'
            ),
        ));

        $this->addElement('text', 'alias', array(
            'label'        => $this->translate('Title'),
            'description' => $this->translate(
                'Usually this title will be shown for this node. Equals name'
                . ' if not given'
            ),
        ));

        $this->addElement('select', 'operator', array(
            'label'        => $this->translate('Operator'),
            'required'     => true,
            'multiOptions' => array(
                '&' => $this->translate('AND'),
                '|' => $this->translate('OR'),
                '!' => $this->translate('NOT'),
                '1' => $this->translate('MIN 1'),
                '2' => $this->translate('MIN 2'),
                '3' => $this->translate('MIN 3'),
                '4' => $this->translate('MIN 4'),
                '5' => $this->translate('MIN 5'),
                '6' => $this->translate('MIN 6'),
                '7' => $this->translate('MIN 7'),
                '8' => $this->translate('MIN 8'),
                '9' => $this->translate('MIN 9'),
            )
        ));

        $this->addElement('select', 'display', array(
            'label'        => $this->translate('Visualization'),
            'required'     => true,
            'description'  => $this->translate(
                'Where to show this process'
            ),
            'multiOptions' => array(
                '1' => $this->translate('Toplevel Process'),
                '0' => $this->translate('Subprocess only'),
            )
        ));

        $this->addElement('text', 'url', array(
            'label'        => $this->translate('Info URL'),
            'description' => $this->translate(
                'URL pointing to more information about this node'
            )
        ));

        $this->addElement('select', 'object_type', array(
            'label'        => $this->translate('Add children'),
            'required'     => $this->node === null || ! $this->node->hasChildren(),
            'ignore'       => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum(
                array(
                    'hosts'   => $this->translate('Host'),
                    'service' => $this->translate('Service'),
                    'process' => $this->translate('Another process'),
                    'include' => $this->translate('External process'),
                )
            )
        ));

        switch ($this->getSentValue('object_type')) {
            case 'hosts':
                $this->addHostsElement();
                break;
            case 'service':
                $this->addHostElement();
                if ($host = $this->getSentValue('host')) {
                    $this->addServicesElement($host);
                }
                break;
            case 'process':
                $this->addProcessesElement();
                break;
        }
    }

    protected function addHostsElement()
    {
        $this->addElement('multiselect', 'children', array(
            'label'        => $this->translate('Hosts'),
            'required'     => true,
            'size'         => 14,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumHostList(),
            'description'  => $this->translate(
                'Hosts that should be part of this business process node'
            )
        ));
    }

    protected function addHostElement()
    {
        $this->addElement('select', 'host', array(
            'label'        => $this->translate('Host'),
            'required'     => true,
            'ignore'       => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum($this->enumHostList()),
        ));
    }

    protected function addServicesElement($host)
    {
        $this->addElement('multiselect', 'children', array(
            'label'        => $this->translate('Services'),
            'required'     => true,
            'size'         => 14,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumServiceList($host),
            'description'  => $this->translate(
                'Services that should be part of this business process node'
            )
        ));
    }

    protected function addProcessesElement()
    {
        $this->addElement('multiselect', 'children', array(
            'label'        => $this->translate('Process nodes'),
            'required'     => true,
            'size'         => 14,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumProcesses(),
            'description'  => $this->translate(
                'Other processes that should be part of this business process node'
            )
        ));
    }

    /**
     * @param MonitoringBackend $backend
     * @return $this
     */
    public function setBackend(MonitoringBackend $backend)
    {
        $this->backend = $backend;
        return $this;
    }

    /**
     * @param BusinessProcess $process
     * @return $this
     */
    public function setProcess(BusinessProcess $process)
    {
        $this->bp = $process;
        $this->setBackend($process->getBackend());
        return $this;
    }

    /**
     * @param BpNode $node
     * @return $this
     */
    public function setNode(BpNode $node)
    {
        $this->node = $node;
        return $this;
    }

    /**
     * @param SessionNamespace $session
     * @return $this
     */
    public function setSession(SessionNamespace $session)
    {
        $this->session = $session;
        return $this;
    }

    protected function enumHostList()
    {
        $names = $this->backend->select()->from('hostStatus', array(
            'hostname'    => 'host_name',
        ))->order('host_name')->getQuery()->fetchColumn();

        // fetchPairs doesn't seem to work when using the same column with
        // different aliases twice
        return array_combine((array) $names, (array) $names);
    }

    protected function enumServiceList($host)
    {
        $query = $this->backend->select()->from(
            'serviceStatus',
            array('service' => 'service_description')
        )->where('host_name', $host);
        $query->order('service_description');
        $names = $query->getQuery()->fetchColumn();

        $services = array();
        foreach ($names as $name) {
            $services[$host . ';' . $name] = $name;
        }

        return $services;
    }

    protected function enumProcesses()
    {
        $list = array();

        foreach ($this->bp->getNodes() as $node) {
            if ($node instanceof BpNode) {
                // TODO: Blacklist parents
                $list[(string) $node] = (string) $node; // display name?
            }
        }

        natsort($list);
        return $list;
    }

    protected function fetchObjectList()
    {
        $this->objectList = array();
        $hosts = $this->backend->select()->from('hostStatus', array(
            'hostname'    => 'host_name',
            'in_downtime' => 'host_in_downtime',
            'ack'         => 'host_acknowledged',
            'state'       => 'host_state'
        ))->order('host_name')->getQuery()->fetchAll();

        $services = $this->backend->select()->from('serviceStatus', array(
            'hostname'    => 'host_name',
            'service'     => 'service_description',
            'in_downtime' => 'service_in_downtime',
            'ack'         => 'service_acknowledged',
            'state'       => 'service_state'
        ))->order('host_name')->order('service_description')->getQuery()->fetchAll();

        foreach ($hosts as $host) {
            $this->objectList[$host->hostname] = array(
                $host->hostname . ';Hoststatus' => 'Host Status'
            );
        }

        foreach ($services as $service) {
            $this->objectList[$service->hostname][
                $service->hostname . ';' . $service->service
            ] = $service->service;
        }

        return $this;
    }

    public function onSuccess()
    {
        $changes = ProcessChanges::construct($this->bp, $this->session);

        $modifications = array();
        $children = $this->getValue('children');
        $alias    = $this->getValue('alias');
        $operator = $this->getValue('operator');
        $display  = $this->getValue('display');
        $url      = $this->getValue('url');
        if (empty($url)) {
            $url = null;
        }
        if (empty($alias)) {
            $alias = null;
        }
        ksort($children);
        // TODO: rename

        if ($node = $this->node) {

            if ($display !== $node->getDisplay()) {
                $modifications['display'] = $display;
            }
            if ($operator !== $node->getOperator()) {
                $modifications['operator'] = $operator;
            }
            if ($children !== $node->getChildNames()) {
                $modifications['childNames'] = $children;
            }
            if ($url !== $node->getInfoUrl()) {
                $modifications['infoUrl'] = $url;
            }
            if ($alias !== $node->getAlias()) {
                $modifications['alias'] = $alias;
            }
        } else {
            $modifications = array(
                'display'    => $display,
                'operator'   => $operator,
                'childNames' => $children,
                'infoUrl'    => $url,
                'alias'      => $alias,
            );
        }
        if (! empty($modifications)) {

            if ($this->node === null) {
                $changes->createNode($this->getValue('name'), $modifications);
            } else {
                $changes->modifyNode($this->node, $modifications);
            }

            Notification::success(
                sprintf(
                    'Process %s has been modified',
                    $this->bp->getName()
                )
            );
        }
    }
}
