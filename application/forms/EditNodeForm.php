<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\MonitoringRestrictions;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Businessprocess\Web\Form\Validator\NoDuplicateChildrenValidator;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;

class EditNodeForm extends QuickForm
{
    use MonitoringRestrictions;

    /** @var MonitoringBackend */
    protected $backend;

    /** @var BpConfig */
    protected $bp;

    /** @var Node */
    protected $node;

    /** @var BpNode */
    protected $parent;

    protected $objectList = array();

    protected $processList = array();

    protected $service;

    protected $host;

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        $this->host = substr($this->getNode()->getName(), 0, strpos($this->getNode()->getName(), ';'));
        if ($this->isService()) {
            $this->service = substr($this->getNode()->getName(), strpos($this->getNode()->getName(), ';') + 1);
        }

        $view = $this->getView();
        $this->addHtml(
            '<h2>' . $view->escape(
                sprintf($this->translate('Modify "%s"'), $this->getNode()->getAlias())
            ) . '</h2>'
        );

        $monitoredNodeType = null;
        if ($this->isService()) {
            $monitoredNodeType = 'service';
        } else {
            $monitoredNodeType = 'host';
        }

        $type = $this->selectNodeType($monitoredNodeType);
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

    protected function isService()
    {
        if (strpos($this->getNode()->getName(), ';Hoststatus')) {
            return false;
        }
        return true;
    }

    protected function addNewProcess()
    {
        $this->addElement('text', 'name', array(
            'label'        => $this->translate('ID'),
            'required'     => true,
            'disabled'     => true,
            'description' => $this->translate(
                'This is the unique identifier of this process'
            ),
        ));

        $this->addElement('text', 'alias', array(
            'label'        => $this->translate('Display Name'),
            'description' => $this->translate(
                'Usually this name will be shown for this node. Equals ID'
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

        $display = $this->getNode()->getDisplay() ?: 1;
        $this->addElement('select', 'display', array(
            'label'        => $this->translate('Visualization'),
            'required'     => true,
            'description'  => $this->translate(
                'Where to show this process'
            ),
            'value' => $display,
            'multiOptions' => array(
                "$display" => $this->translate('Toplevel Process'),
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
    protected function selectNodeType($monitoredNodeType = null)
    {
        if ($this->hasParentNode()) {
            $this->addElement('hidden', 'node_type', [
                'disabled'      => true,
                'ignore'        => true,
                'decorators'    => ['ViewHelper'],
                'value'         => $monitoredNodeType
            ]);

            return $monitoredNodeType;
        } elseif (! $this->hasProcesses()) {
            $this->addElement('hidden', 'node_type', array(
                'ignore'     => true,
                'decorators' => array('ViewHelper'),
                'value'      => 'new-process'
            ));

            return 'new-process';
        }
    }

    protected function selectHost()
    {
        $this->addElement('select', 'children', array(
            'required'      => true,
            'value'         => $this->getNode()->getName(),
            'multiOptions'  => $this->enumHostList(),
            'label'         => $this->translate('Host'),
            'description'   => $this->translate('The host for this business process node'),
            'validators'    => [[new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true]]
        ));
    }

    protected function selectService()
    {
        $this->addHostElement();

        if ($this->getSentValue('hosts') === null) {
            $this->addServicesElement($this->host);
            $this->addServiceOverrideCheckbox();
            if (! empty($this->node->getStateOverrides()) || $this->getSentValue('service_override') === '1') {
                $this->addServiceOverrideElement();
            }
        } elseif ($host = $this->getSentValue('hosts')) {
            $this->addServicesElement($host);
            $this->addServiceOverrideCheckbox();
            if ($this->getSentValue('service_override') === '1') {
                $this->addServiceOverrideElement();
            }
        } else {
            $this->setSubmitLabel($this->translate('Next'));
        }
    }

    protected function addHostElement()
    {
        $this->addElement('select', 'hosts', array(
            'label'        => $this->translate('Host'),
            'required'     => true,
            'ignore'       => true,
            'class'        => 'autosubmit',
            'multiOptions' => $this->optionalEnum($this->enumHostForServiceList()),
        ));

        $this->getElement('hosts')->setValue($this->host);
    }

    protected function addServicesElement($host)
    {
        $this->addElement('select', 'children', array(
            'required'      => true,
            'value'         => $this->getNode()->getName(),
            'multiOptions'  => $this->enumServiceList($host),
            'label'         => $this->translate('Service'),
            'description'   => $this->translate('The service for this business process node'),
            'validators'    => [[new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true]]
        ));
    }

    protected function addServiceOverrideCheckbox()
    {
        $this->addElement('checkbox', 'service_override', [
            'ignore'        => true,
            'class'         => 'autosubmit',
            'value'         => ! empty($this->node->getStateOverrides()),
            'label'         => $this->translate('Override Service State'),
            'description'   => $this->translate('Enable service state overrides')
        ]);
    }

    protected function addServiceOverrideElement()
    {
        $this->addElement('stateOverrides', 'stateOverrides', [
            'required'  => true,
            'states'    => $this->enumServiceStateList(),
            'value'     => $this->node->getStateOverrides(),
            'label'     => $this->translate('State Overrides')
        ]);
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
        $names = $this->backend
            ->select()
            ->from('hostStatus', ['hostname' => 'host_name'])
            ->applyFilter($this->getRestriction('monitoring/filter/objects'))
            ->order('host_name')
            ->getQuery()
            ->fetchColumn();

        // fetchPairs doesn't seem to work when using the same column with
        // different aliases twice

        return array_combine((array) $names, (array) $names);
    }

    protected function enumHostList()
    {
        $names = $this->backend
            ->select()
            ->from('hostStatus', ['hostname' => 'host_name'])
            ->applyFilter($this->getRestriction('monitoring/filter/objects'))
            ->order('host_name')
            ->getQuery()
            ->fetchColumn();

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
        $names = $this->backend
            ->select()
            ->from('serviceStatus', ['service' => 'service_description'])
            ->where('host_name', $host)
            ->applyFilter($this->getRestriction('monitoring/filter/objects'))
            ->order('service_description')
            ->getQuery()
            ->fetchColumn();

        $services = array();
        foreach ($names as $name) {
            $services[$host . ';' . $name] = $name;
        }

        return $services;
    }

    protected function enumServiceStateList()
    {
        $serviceStateList = [
            0 => $this->translate('OK'),
            1 => $this->translate('WARNING'),
            2 => $this->translate('CRITICAL'),
            3 => $this->translate('UNKNOWN'),
            99 => $this->translate('PENDING'),
        ];

        return $serviceStateList;
    }

    protected function hasProcesses()
    {
        return count($this->enumProcesses()) > 0;
    }

    protected function enumProcesses()
    {
        $list = array();

        $parents = array();

        if ($this->hasParentNode()) {
            $this->collectAllParents($this->parent, $parents);
            $parents[$this->parent->getName()] = $this->parent;
        }

        foreach ($this->bp->getNodes() as $node) {
            if ($node instanceof BpNode && ! isset($parents[$node->getName()])) {
                $list[$node->getName()] = $node->getName(); // display name?
            }
        }

        if (! $this->bp->getMetadata()->isManuallyOrdered()) {
            natcasesort($list);
        }
        return $list;
    }

    /**
     * Collect the given node's parents recursively into the given array by their names
     *
     * @param   BpNode      $node
     * @param   BpNode[]    $parents
     */
    protected function collectAllParents(BpNode $node, array &$parents)
    {
        foreach ($node->getParents() as $parent) {
            $parents[$parent->getName()] = $parent;
            $this->collectAllParents($parent, $parents);
        }
    }

    /**
     * @param Node $node
     * @return $this
     */
    public function setNode(Node $node)
    {
        $this->node = $node;
        return $this;
    }

    public function getNode()
    {
        return $this->node;
    }

    public function onSuccess()
    {
        $changes = ProcessChanges::construct($this->bp, $this->session);

        $changes->deleteNode($this->node, $this->parent->getName());

        switch ($this->getValue('node_type')) {
            case 'service':
                $properties = $this->getValues();
                unset($properties['children']);
                $services = [$this->getValue('children') => $properties];
                $changes->addChildrenToNode($services, $this->parent);
                break;
            case 'host':
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

    public function isValid($data)
    {
        // Don't allow to override disabled elements. This is probably too harsh
        // but also wouldn't be necessary if this would be a Icinga\Web\Form...
        foreach ($this->getElements() as $element) {
            /** @var \Zend_Form_Element $element */
            if ($element->getAttrib('disabled')) {
                $data[$element->getName()] = $element->getValue();
            }
        }

        return parent::isValid($data);
    }
}
