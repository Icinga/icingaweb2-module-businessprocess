<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Common\EnumList;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Businessprocess\Web\Form\Validator\NoDuplicateChildrenValidator;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;
use ipl\Sql\Connection as IcingaDbConnection;

class EditNodeForm extends QuickForm
{
    use EnumList;

    /** @var MonitoringBackend|IcingaDbConnection */
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
            'description'  => $this->translate('This is the unique identifier of this process'),
            'validators'   => [
                ['Callback', true, [
                    'callback'  => function ($value) {
                        if ($this->hasParentNode()) {
                            return ! $this->parent->hasChild($value);
                        }

                        return ! $this->bp->hasRootNode($value);
                    },
                    'messages'  => [
                        'callbackValue' => $this->translate('%value% is already defined in this process')
                    ]
                ]],
                ['Callback', true, [
                    'callback' => function ($value) {
                        return strpos($value, ';') === false;
                    },
                    'messages' => [
                        'callbackValue' => $this->translate('Semicolon in id is not allowed')
                    ]
                ]]
            ]
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
            'multiOptions' => Node::getOperators()
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
            'validators'    => [
                [new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true],
                ['Callback', true, [
                    'callback' => function ($value) {
                        return substr_count($value, ';') === 1;
                    },
                    'messages' => [
                        'callbackValue' => $this->translate('Host containing semicolon in name is not allowed')
                    ]
                ]]
            ]
        ));

        $this->addHostOverrideCheckbox();
        $hostOverrideSent = $this->getSentValue('host_override');
        if ($hostOverrideSent === '1'
            || ($hostOverrideSent === null && $this->getElement('host_override')->isChecked())
        ) {
            $this->addHostOverrideElement();
        }
    }

    protected function selectService()
    {
        $this->addHostElement();

        if ($this->getSentValue('hosts') === null) {
            $this->addServicesElement($this->host);
            $this->addServiceOverrideCheckbox();
            if ($this->getElement('service_override')->isChecked() || $this->getSentValue('service_override') === '1') {
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
            'validators'   => [
                ['Callback', true, [
                    'callback' => function ($value) {
                        return substr_count($value, ';') === 0;
                    },
                    'messages' => [
                        'callbackValue' => $this->translate('Host containing semicolon in name is not allowed')
                    ]
                ]]
            ]
        ));

        $this->getElement('hosts')->setValue($this->host);
    }

    protected function addHostOverrideCheckbox()
    {
        $this->addElement('checkbox', 'host_override', [
            'ignore'        => true,
            'class'         => 'autosubmit',
            'value'         => ! empty($this->parent->getStateOverrides($this->node->getName())),
            'label'         => $this->translate('Override Host State'),
            'description'   => $this->translate('Enable host state overrides')
        ]);
    }

    protected function addHostOverrideElement()
    {
        $this->addElement('stateOverrides', 'stateOverrides', [
            'required'  => true,
            'states'    => $this->enumHostStateList(),
            'value'     => $this->parent->getStateOverrides($this->node->getName()),
            'label'     => $this->translate('State Overrides')
        ]);
    }

    protected function addServicesElement($host)
    {
        $this->addElement('select', 'children', array(
            'required'      => true,
            'value'         => $this->getNode()->getName(),
            'multiOptions'  => $this->enumServiceList($host),
            'label'         => $this->translate('Service'),
            'description'   => $this->translate('The service for this business process node'),
            'validators'    => [
                [new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true],
                ['Callback', true, [
                    'callback' => function ($value) {
                        return substr_count($value, ';') === 1;
                    },
                    'messages' => [
                        'callbackValue' => $this->translate('Service containing semicolon in name is not allowed')
                    ]
                ]]
            ]
        ));
    }

    protected function addServiceOverrideCheckbox()
    {
        $this->addElement('checkbox', 'service_override', [
            'ignore'        => true,
            'class'         => 'autosubmit',
            'value'         => ! empty($this->parent->getStateOverrides($this->node->getName())),
            'label'         => $this->translate('Override Service State'),
            'description'   => $this->translate('Enable service state overrides')
        ]);
    }

    protected function addServiceOverrideElement()
    {
        $this->addElement('stateOverrides', 'stateOverrides', [
            'required'  => true,
            'states'    => $this->enumServiceStateList(),
            'value'     => $this->parent->getStateOverrides($this->node->getName()),
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
     * @param MonitoringBackend|IcingaDbConnection $backend
     * @return $this
     */
    public function setBackend($backend)
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
            case 'host':
            case 'service':
                $stateOverrides = $this->getValue('stateOverrides') ?: [];
                if (! empty($stateOverrides)) {
                    $stateOverrides = array_merge(
                        $this->parent->getStateOverrides(),
                        [$this->getValue('children') => $stateOverrides]
                    );
                } else {
                    $stateOverrides = $this->parent->getStateOverrides();
                    unset($stateOverrides[$this->getValue('children')]);
                }

                $changes->modifyNode($this->parent, ['stateOverrides' => $stateOverrides]);
                // Fallthrough
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
