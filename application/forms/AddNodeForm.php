<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Data\Filter\Filter;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\Common\EnumList;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Storage\Storage;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Businessprocess\Web\Form\Validator\NoDuplicateChildrenValidator;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;

class AddNodeForm extends QuickForm
{
    use EnumList;

    /** @var MonitoringBackend|IcingadbDatabase */
    protected $backend;

    /** @var Storage */
    protected $storage;

    /** @var BpConfig */
    protected $bp;

    /** @var BpNode */
    protected $parent;

    protected $objectList = array();

    protected $processList = array();

    /** @var SessionNamespace */
    protected $session;

    /** @var  string $backendName */
    protected $backendName;

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
            case 'hosts_from_filter':
                $this->selectHostsFromFilter();
                break;
            case 'services_from_filter':
                $this->selectServicesFromFilter();
                break;
            case null:
                $this->setSubmitLabel($this->translate('Next'));
                return;
        }
    }

    protected function addNewProcess()
    {
        $this->addElement('text', 'name', array(
            'label'        => $this->translate('ID'),
            'required'     => true,
            'description' => $this->translate(
                'This is the unique identifier of this process'
            ),
            'validators'    => [
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

        $display = 1;
        if ($this->bp->getMetadata()->isManuallyOrdered() && !$this->bp->isEmpty()) {
            $rootNodes = $this->bp->getRootNodes();
            $display = end($rootNodes)->getDisplay() + 1;
        }
        $this->addElement('select', 'display', array(
            'label'        => $this->translate('Visualization'),
            'required'     => true,
            'description'  => $this->translate(
                'Where to show this process'
            ),
            'value' => $this->hasParentNode() ? '0' : "$display",
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
    protected function selectNodeType()
    {
        $types = array();
        if ($this->hasParentNode()) {
            $types['host'] = $this->translate('Host');
            $types['service'] = $this->translate('Service');
            $types['hosts_from_filter'] = $this->translate('Hosts from filter');
            $types['services_from_filter'] = $this->translate('Services from filter');
        } elseif (! $this->hasProcesses()) {
            $this->addElement('hidden', 'node_type', array(
                'ignore'     => true,
                'decorators' => array('ViewHelper'),
                'value'      => 'new-process'
            ));

            return 'new-process';
        }

        if ($this->hasProcesses() || ($this->hasParentNode() && $this->hasMoreConfigs())) {
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
        $this->addElement('multiselect', 'children', [
            'label'        => $this->translate('Hosts'),
            'required'     => true,
            'size'         => 8,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumHostList(),
            'description'  => $this->translate(
                'Hosts that should be part of this business process node'
            ),
            'validators'    => [[new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true]]
        ]);

        $this->addHostOverrideCheckbox();
        if ($this->getSentValue('host_override') === '1') {
            $this->addHostOverrideElement();
        }
    }

    protected function selectService()
    {
        $this->addHostElement();
        if ($host = $this->getSentValue('host')) {
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
        $this->addElement('multiselect', 'children', [
            'label'        => $this->translate('Services'),
            'required'     => true,
            'size'         => 8,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumServiceList($host),
            'description'  => $this->translate(
                'Services that should be part of this business process node'
            ),
            'validators'    => [[new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true]]
        ]);
    }

    protected function addFilteredHostsElement($filter)
    {
        $this->addElement('submit', 'refresh', [
            'label'        => $this->translate('Refresh'),
            'class'        => 'refresh-filter'
        ]);
        $this->addElement('multiselect', 'children', [
            'label'        => $this->translate('Hosts'),
            'required'     => true,
            'size'         => 8,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumHostListByFilter($filter),
            'description'  => $this->translate(
                'Hosts that should be part of this business process node'
            ),
            'validators'    => [[new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true]]
        ]);
    }

    protected function addFilteredServicesElement($filter)
    {
        $this->addElement('submit', 'refresh', [
            'label'        => $this->translate('Refresh'),
            'class'        => 'refresh-filter'
        ]);
        $this->addElement('multiselect', 'children', [
            'label'        => $this->translate('Services'),
            'required'     => true,
            'size'         => 8,
            'style'        => 'width: 25em',
            'multiOptions' => $this->enumServiceListByFilter($filter),
            'description'  => $this->translate(
                'Services that should be part of this business process node'
            ),
            'validators'    => [[new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true]]
        ]);
    }

    protected function addFilterElement()
    {
        $this->addElement('text', 'filter', array(
            'label'        => $this->translate('Filter'),
            'required'     => true,
            'ignore'       => true
        ));
    }

    protected function addFileElement()
    {
        $this->addElement('select', 'file', [
            'label'         => $this->translate('File'),
            'required'      => true,
            'ignore'        => true,
            'value'         => $this->bp->getName(),
            'class'         => 'autosubmit',
            'multiOptions'  => $this->optionalEnum($this->enumConfigs()),
            'description'   => $this->translate(
                'Choose a different configuration file to import its processes'
            )
        ]);
    }

    protected function addHostOverrideCheckbox()
    {
        $this->addElement('checkbox', 'host_override', [
            'ignore'        => true,
            'class'         => 'autosubmit',
            'label'         => $this->translate('Override Host State'),
            'description'   => $this->translate('Enable host state overrides')
        ]);
    }

    protected function addHostOverrideElement()
    {
        $this->addElement('stateOverrides', 'stateOverrides', [
            'required'  => true,
            'label'     => $this->translate('State Overrides'),
            'states'    => $this->enumHostStateList()
        ]);
    }

    protected function addServiceOverrideCheckbox()
    {
        $this->addElement('checkbox', 'service_override', [
            'ignore'        => true,
            'class'         => 'autosubmit',
            'label'         => $this->translate('Override Service State'),
            'description'   => $this->translate('Enable service state overrides')
        ]);
    }

    protected function addServiceOverrideElement()
    {
        $this->addElement('stateOverrides', 'stateOverrides', [
            'required'  => true,
            'label'     => $this->translate('State Overrides'),
            'states'    => $this->enumServiceStateList()
        ]);
    }

    protected function selectHostsFromFilter()
    {
        $this->addFilterElement();
        if ($filter = $this->getSentValue('filter')) {
            $this->addFilteredHostsElement($filter);
        } else {
            $this->setSubmitLabel($this->translate('Next'));
        }
    }

    protected function selectServicesFromFilter()
    {
        $this->addFilterElement();
        if ($filter = $this->getSentValue('filter')) {
            $this->addFilteredServicesElement($filter);
        } else {
            $this->setSubmitLabel($this->translate('Next'));
        }
    }

    protected function selectProcess()
    {
        if ($this->hasParentNode()) {
            $this->addFileElement();
        }

        if (($file = $this->getSentValue('file')) || !$this->hasParentNode()) {
            $this->addElement('multiselect', 'children', [
                'label'        => $this->translate('Process nodes'),
                'required'     => true,
                'size'         => 8,
                'style'        => 'width: 25em',
                'multiOptions' => $this->enumProcesses($file),
                'description'  => $this->translate(
                    'Other processes that should be part of this business process node'
                ),
                'validators'    => [[new NoDuplicateChildrenValidator($this, $this->bp, $this->parent), true]]
            ]);
        } else {
            $this->setSubmitLabel($this->translate('Next'));
        }
    }

    /**
     * @param MonitoringBackend|IcingadbDatabase $backend
     * @return $this
     */
    public function setBackend($backend)
    {
        $this->backend = $backend;
        return $this;
    }

    /**
     * @param Storage $storage
     * @return $this
     */
    public function setStorage(Storage $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * @param BpConfig $process
     * @return $this
     */
    public function setProcess(BpConfig $process)
    {
        $this->bp = $process;
        $this->backendName = $process->getBackendName();
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

    protected function enumHostStateList()
    {
        $hostStateList = [
            0 => $this->translate('UP'),
            1 => $this->translate('DOWN'),
            99 => $this->translate('PENDING')
        ];

        return $hostStateList;
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

    protected function enumHostListByFilter($filter)
    {
        $names = $this->backend
            ->select()
            ->from('hostStatus', ['hostname' => 'host_name'])
            ->applyFilter(Filter::fromQueryString($filter))
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

    protected function enumServiceListByFilter($filter)
    {
        $objects = $this->backend
            ->select()
            ->from('serviceStatus', ['host' => 'host_name', 'service' => 'service_description'])
            ->applyFilter(Filter::fromQueryString($filter))
            ->applyFilter($this->getRestriction('monitoring/filter/objects'))
            ->order('service_description')
            ->getQuery()
            ->fetchAll();

        $services = array();
        foreach ($objects as $object) {
            $services[$object->host . ';' . $object->service] = $object->host . ':' . $object->service;
        }

        return $services;
    }

    protected function hasProcesses()
    {
        return count($this->enumProcesses()) > 0;
    }

    /**
     * @param string $file
     * @return array
     */
    protected function enumProcesses($file = null)
    {
        $list = array();

        $parents = array();

        $differentFile = $file !== null && $file !== $this->bp->getName();

        if (! $differentFile && $this->hasParentNode()) {
            $this->collectAllParents($this->parent, $parents);
            $parents[$this->parent->getName()] = $this->parent;
        }

        $bp = $this->bp;
        if ($differentFile) {
            $bp = $this->storage->loadProcess($file);
        }

        foreach ($bp->getNodes() as $node) {
            if (! $node instanceof ImportedNode && $node instanceof BpNode && ! isset($parents[$node->getName()])) {
                $name = $node->getName();
                if ($differentFile) {
                    $name = '@' . $file . ':' . $name;
                }

                $list[$name] = $node->getName(); // display name?
            }
        }

        if (! $this->bp->getMetadata()->isManuallyOrdered()) {
            natcasesort($list);
        }
        return $list;
    }

    protected function hasMoreConfigs()
    {
        $configs = $this->enumConfigs();
        return !empty($configs);
    }

    protected function enumConfigs()
    {
        return $this->storage->listProcesses();
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

    public function onSuccess()
    {
        $changes = ProcessChanges::construct($this->bp, $this->session);
        switch ($this->getValue('node_type')) {
            case 'host':
            case 'service':
                $stateOverrides = $this->getValue('stateOverrides');
                if (! empty($stateOverrides)) {
                    $childOverrides = [];
                    foreach ($this->getValue('children') as $service) {
                        $childOverrides[$service] = $stateOverrides;
                    }

                    $changes->modifyNode($this->parent, [
                        'stateOverrides' => array_merge($this->parent->getStateOverrides(), $childOverrides)
                    ]);
                }

                // Fallthrough
            case 'process':
            case 'hosts_from_filter':
            case 'services_from_filter':
                if ($this->hasParentNode()) {
                    $changes->addChildrenToNode($this->getValue('children'), $this->parent);
                } else {
                    foreach ($this->getValue('children') as $nodeName) {
                        $changes->copyNode($nodeName);
                    }
                }

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
