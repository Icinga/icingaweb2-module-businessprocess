<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;

class AddNodeForm extends QuickForm
{
    /** @var MonitoringBackend */
    protected $backend;

    /** @var BpConfig */
    protected $bp;

    /** @var BpNode */
    protected $parent;

    protected $objectList = array();

    protected $processList = array();

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        $view = $this->getView();
        if ($this->hasParentNode()) {
            $this->addHtml(
                '<h2>' . $view->escape(
                    sprintf($this->translate('Add a node to %s'), $this->parent->getAlias())
                ) . '</h2>'
            );
        } else {
            $this->addHtml(
                '<h2>' . $this->translate('Add a new root node') . '</h2>'
            );
        }

        $type = $this->selectNodeType();
        switch ($type) {
            case 'host':
                $this->selectHost();
                break;
            case 'service':
                $this->selectService();
                break;
            case 'process':
                $this->selectProcess();
                break;
            case 'new-process':
                $this->addNewProcess();
                break;
            case null:
                $this->setSubmitLabel($this->translate('Next'));
                return;
        }
    }

    protected function addNewProcess()
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
                '<' => $this->translate('DEG'),
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
            'value' => $this->hasParentNode() ? '0' : '1',
            'multiOptions' => array(
                '1' => $this->translate('Toplevel Process'),
                '0' => $this->translate('Subprocess only'),
            )
        ));

        $this->addElement('text', 'infoUrl', array(
            'label'        => $this->translate('Info URL'),
            'description' => $this->translate(
                'URL pointing to more information about this node'
            )
        ));
    }

    /**
     * @return string|null
     */
    protected function selectNodeType()
    {
        $types = array();
        if ($this->hasParentNode()) {
            $types['host'] = $this->translate('Host');
            $types['service'] = $this->translate('Service');
        } elseif (! $this->hasProcesses()) {
            $this->addElement('hidden', 'node_type', array(
                'ignore'     => true,
                'decorators' => array('ViewHelper'),
                'value'      => 'new-process'
            ));

            return 'new-process';
        }

        if ($this->hasProcesses()) {
            $types['process'] = $this->translate('Existing Process');
        }

        $types['new-process'] = $this->translate('New Process Node');

        $this->addElement('select', 'node_type', array(
            'label'        => $this->translate('Node type'),
            'required'     => true,
            'description'  => $this->translate(
                'The node type you want to add'
            ),
            'ignore'       => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum($types)
        ));

        return $this->getSentValue('node_type');
    }

    protected function selectHost()
    {
        $this->addElement('multiselect', 'children', array(
            'label'        => $this->translate('Hosts'),
            'required'     => true,
            'size'         => 8,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumHostList(),
            'description'  => $this->translate(
                'Hosts that should be part of this business process node'
            )
        ));
    }

    protected function selectService()
    {
        $this->addHostElement();
        if ($host = $this->getSentValue('host')) {
            $this->addServicesElement($host);
        } else {
            $this->setSubmitLabel($this->translate('Next'));
        }
    }

    protected function addHostElement()
    {
        $this->addElement('select', 'host', array(
            'label'        => $this->translate('Host'),
            'required'     => true,
            'ignore'       => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum($this->enumHostForServiceList()),
        ));
    }

    protected function addServicesElement($host)
    {
        $this->addElement('multiselect', 'children', array(
            'label'        => $this->translate('Services'),
            'required'     => true,
            'size'         => 8,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumServiceList($host),
            'description'  => $this->translate(
                'Services that should be part of this business process node'
            )
        ));
    }

    protected function selectProcess()
    {
        $this->addElement('multiselect', 'children', array(
            'label'        => $this->translate('Process nodes'),
            'required'     => true,
            'size'         => 8,
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
     * @param BpConfig $process
     * @return $this
     */
    public function setProcess(BpConfig $process)
    {
        $this->bp = $process;
        $this->setBackend($process->getBackend());
        return $this;
    }

    /**
     * @param BpNode|null $node
     * @return $this
     */
    public function setParentNode(BpNode $node = null)
    {
        $this->parent = $node;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasParentNode()
    {
        return $this->parent !== null;
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

    protected function enumHostForServiceList()
    {
        $names = $this->backend->select()->from('hostStatus', array(
            'hostname'    => 'host_name',
        ))->order('host_name')->getQuery()->fetchColumn();

        // fetchPairs doesn't seem to work when using the same column with
        // different aliases twice

        return array_combine((array) $names, (array) $names);
    }

    protected function enumHostList()
    {
        $names = $this->backend->select()->from('hostStatus', array(
            'hostname'    => 'host_name',
        ))->order('host_name')->getQuery()->fetchColumn();

        // fetchPairs doesn't seem to work when using the same column with
        // different aliases twice
        $res = array();
        $suffix = ';Hoststatus';
        foreach ($names as $name) {
            $res[$name . $suffix] = $name;
        }

        return $res;
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

    protected function hasProcesses()
    {
        return count($this->enumProcesses()) > 0;
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
        switch ($this->getValue('node_type')) {
            case 'host':
            case 'service':
            case 'process':
                $changes->addChildrenToNode($this->getValue('children'), $this->parent);
                break;
            case 'new-process':
                $properties = $this->getValues();
                unset($properties['name']);
                if ($this->hasParentNode()) {
                    $properties['parentName'] = $this->parent->getName();
                }
                $changes->createNode($this->getValue('name'), $properties);
                break;
        }

        // Trigger session destruction to make sure it get's stored.
        // TODO: figure out why this is necessary, might be an unclean shutdown on redirect
        unset($changes);

        parent::onSuccess();
    }
}
