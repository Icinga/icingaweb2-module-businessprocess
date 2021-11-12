<?php

namespace Icinga\Module\Businessprocess;

use Exception;
use Icinga\Application\Config;
use Icinga\Application\Modules\Module;
use Icinga\Exception\IcingaException;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\Exception\NestingError;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

class BpConfig
{
    use IcingadbDatabase;

    const SOFT_STATE = 0;

    const HARD_STATE = 1;

    /**
     * Name of the configured monitoring backend
     *
     * @var string
     */
    protected $backendName;

    /**
     * Backend to retrieve states from
     *
     * @var MonitoringBackend|IcingadbDatabase
     */
    protected $backend;

    /**
     * @var LegacyStorage
     */
    protected $storage;

    /** @var  Metadata */
    protected $metadata;

    /**
     * Business process name
     *
     * @var string
     */
    protected $name;

    /**
     * Business process title
     *
     * @var string
     */
    protected $title;

    /**
     * State type, soft or hard
     *
     * @var int
     */
    protected $state_type;

    /**
     * Warnings, usually filled at process build time
     *
     * @var array
     */
    protected $warnings = array();

    /**
     * Errors, usually filled at process build time
     *
     * @var array
     */
    protected $errors = array();

    /**
     * All used node objects
     *
     * @var array
     */
    protected $nodes = array();

    /**
     * Root node objects
     *
     * @var array
     */
    protected $root_nodes = array();

    /**
     * Imported nodes
     *
     * @var ImportedNode[]
     */
    protected $importedNodes = [];

    /**
     * Imported configs
     *
     * @var BpConfig[]
     */
    protected $importedConfigs = [];

    /**
     * All host names { 'hostA' => true, ... }
     *
     * @var array
     */
    protected $hosts = array();

    /** @var bool Whether catchable errors should be thrown nonetheless */
    protected $throwErrors = false;

    protected $loopDetection = array();

    /**
     * Applied state simulation
     *
     * @var Simulation
     */
    protected $simulation;

    protected $changeCount = 0;

    protected $simulationCount = 0;

    /** @var ProcessChanges */
    protected $appliedChanges;

    public function __construct()
    {
    }

    /**
     * Retrieve metadata for this configuration
     *
     * @return Metadata
     */
    public function getMetadata()
    {
        if ($this->metadata === null) {
            $this->metadata = new Metadata($this->name);
        }

        return $this->metadata;
    }

    /**
     * Set metadata
     *
     * @param Metadata $metadata
     *
     * @return $this
     */
    public function setMetadata(Metadata $metadata)
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Apply pending process changes
     *
     * @param ProcessChanges $changes
     *
     * @return $this
     */
    public function applyChanges(ProcessChanges $changes)
    {
        $cnt = 0;
        foreach ($changes->getChanges() as $change) {
            $cnt++;
            $change->applyTo($this);
        }
        $this->changeCount = $cnt;

        $this->appliedChanges = $changes;

        return $this;
    }

    /**
     * Apply a state simulation
     *
     * @param Simulation $simulation
     *
     * @return $this
     */
    public function applySimulation(Simulation $simulation)
    {
        $cnt = 0;

        foreach ($simulation->simulations() as $node => $s) {
            if (! $this->hasNode($node)) {
                continue;
            }
            $cnt++;
            $this->getNode($node)
                 ->setState($s->state)
                 ->setAck($s->acknowledged)
                 ->setDowntime($s->in_downtime)
                 ->setMissing(false);
        }

        $this->simulationCount = $cnt;

        return $this;
    }

    /**
     * Number of applied changes
     *
     * @return int
     */
    public function countChanges()
    {
        return $this->changeCount;
    }

    /**
     * Whether changes have been applied to this configuration
     *
     * @return int
     */
    public function hasChanges()
    {
        return $this->countChanges() > 0;
    }

    /**
     * @param $name
     *
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getHtmlId()
    {
        return 'businessprocess-' . preg_replace('/[\r\n\t\s]/', '_', $this->getName());
    }

    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle()
    {
        return $this->getMetadata()->getTitle();
    }

    public function hasTitle()
    {
        return $this->getMetadata()->has('Title');
    }

    public function getBackendName()
    {
        return $this->getMetadata()->get('Backend');
    }

    public function hasBackendName()
    {
        return $this->getMetadata()->has('Backend');
    }

    public function setBackend($backend)
    {
        $this->backend = $backend;
        return $this;
    }

    public function getBackend()
    {
        if ($this->backend === null) {
            if ($this->getBackendName() === '_icingadb' ||
                (Module::exists('icingadb') && IcingadbSupport::useIcingaDbAsBackend())
            ) {
                $this->backend = $this->getDb();
            } else {
                $this->backend = MonitoringBackend::instance(
                    $this->getBackendName()
                );
            }
        }

        return $this->backend;
    }

    public function hasBackend()
    {
        return $this->backend !== null;
    }

    public function hasBeenChanged()
    {
        return false;
    }

    public function hasSimulations()
    {
        return $this->countSimulations() > 0;
    }

    public function countSimulations()
    {
        return $this->simulationCount;
    }

    public function clearAppliedChanges()
    {
        if ($this->appliedChanges !== null) {
            $this->appliedChanges->clear();
        }
        return $this;
    }

    public function getStateType()
    {
        if ($this->state_type === null) {
            if ($this->getMetadata()->has('Statetype')) {
                switch ($this->getMetadata()->get('Statetype')) {
                    case 'hard':
                        $this->state_type = self::HARD_STATE;
                        break;
                    case 'soft':
                        $this->state_type = self::SOFT_STATE;
                        break;
                }
            } else {
                $this->state_type = self::HARD_STATE;
            }
        }

        return $this->state_type;
    }

    public function useSoftStates()
    {
        $this->state_type = self::SOFT_STATE;
        return $this;
    }

    public function useHardStates()
    {
        $this->state_type = self::HARD_STATE;
        return $this;
    }

    public function usesSoftStates()
    {
        return $this->getStateType() === self::SOFT_STATE;
    }

    public function usesHardStates()
    {
        return $this->getStateType() === self::HARD_STATE;
    }

    public function addRootNode($name)
    {
        $this->root_nodes[$name] = $this->getNode($name);
        return $this;
    }

    public function removeRootNode($name)
    {
        if ($this->isRootNode($name)) {
            unset($this->root_nodes[$name]);
        }

        return $this;
    }

    public function isRootNode($name)
    {
        return array_key_exists($name, $this->root_nodes);
    }

    /**
     * @return BpNode[]
     */
    public function getChildren()
    {
        return $this->getRootNodes();
    }

    /**
     * @return int
     */
    public function countChildren()
    {
        return count($this->root_nodes);
    }

    /**
     * @return BpNode[]
     */
    public function getRootNodes()
    {
        if ($this->getMetadata()->isManuallyOrdered()) {
            uasort($this->root_nodes, function (BpNode $a, BpNode $b) {
                $a = $a->getDisplay();
                $b = $b->getDisplay();
                return $a > $b ? 1 : ($a < $b ? -1 : 0);
            });
        } else {
            ksort($this->root_nodes, SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $this->root_nodes;
    }

    public function listRootNodes()
    {
        $names = array_keys($this->root_nodes);
        if ($this->getMetadata()->isManuallyOrdered()) {
            uasort($names, function ($a, $b) {
                $a = $this->root_nodes[$a]->getDisplay();
                $b = $this->root_nodes[$b]->getDisplay();
                return $a > $b ? 1 : ($a < $b ? -1 : 0);
            });
        } else {
            natcasesort($names);
        }

        return $names;
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function hasNode($name)
    {
        if (array_key_exists($name, $this->nodes)) {
            return true;
        } elseif ($name[0] === '@') {
            list($configName, $nodeName) = preg_split('~:\s*~', substr($name, 1), 2);
            return $this->getImportedConfig($configName)->hasNode($nodeName);
        }

        return false;
    }

    public function hasRootNode($name)
    {
        return array_key_exists($name, $this->root_nodes);
    }

    public function createService($host, $service)
    {
        $node = new ServiceNode(
            (object) array(
                'hostname' => $host,
                'service'  => $service
            )
        );
        $node->setBpConfig($this);
        $this->nodes[$host . ';' . $service] = $node;
        $this->hosts[$host] = true;
        return $node;
    }

    public function createHost($host)
    {
        $node = new HostNode((object) array('hostname' => $host));
        $node->setBpConfig($this);
        $this->nodes[$host . ';Hoststatus'] = $node;
        $this->hosts[$host] = true;
        return $node;
    }

    public function calculateAllStates()
    {
        foreach ($this->getRootNodes() as $node) {
            $node->getState();
        }

        return $this;
    }

    public function clearAllStates()
    {
        foreach ($this->getBpNodes() as $node) {
            $node->clearState();
        }

        return $this;
    }

    public function listInvolvedHostNames(&$usedConfigs = null)
    {
        $hosts = $this->hosts;
        if (! empty($this->importedNodes)) {
            $usedConfigs[$this->getName()] = true;
            foreach ($this->importedNodes as $node) {
                if (isset($usedConfigs[$node->getConfigName()])) {
                    continue;
                }

                $hosts += array_flip($node->getBpConfig()->listInvolvedHostNames($usedConfigs));
            }
        }

        return array_keys($hosts);
    }

    /**
     * Create and attach a new process (BpNode)
     *
     * @param string $name     Process name
     * @param string $operator Operator (defaults to &)
     *
     * @return BpNode
     */
    public function createBp($name, $operator = '&')
    {
        $node = new BpNode((object) array(
            'name'        => $name,
            'operator'    => $operator,
            'child_names' => array(),
        ));
        $node->setBpConfig($this);

        $this->addNode($name, $node);
        return $node;
    }

    public function createMissingBp($name)
    {
        return $this->createBp($name)->setMissing();
    }

    public function getMissingChildren()
    {
        $missing = array();
        foreach ($this->getRootNodes() as $root) {
            $missing += $root->getMissingChildren();
        }

        return $missing;
    }

    public function createImportedNode($config, $name = null)
    {
        $params = (object) array('configName' => $config);
        if ($name !== null) {
            $params->node = $name;
        }

        $node = new ImportedNode($this, $params);
        $this->importedNodes[$node->getName()] = $node;
        $this->nodes[$node->getName()] = $node;
        return $node;
    }

    public function getImportedNodes()
    {
        return $this->importedNodes;
    }

    public function getImportedConfig($name)
    {
        if (! isset($this->importedConfigs[$name])) {
            $import = $this->storage()->loadProcess($name);

            if ($this->usesSoftStates()) {
                $import->useSoftStates();
            } else {
                $import->useHardStates();
            }

            $this->importedConfigs[$name] = $import;
        }

        return $this->importedConfigs[$name];
    }

    public function listInvolvedConfigs(&$configs = null)
    {
        if ($configs === null) {
            $configs[$this->getName()] = $this;
        }

        foreach ($this->importedNodes as $node) {
            if (! isset($configs[$node->getConfigName()])) {
                $config = $node->getBpConfig();
                $configs[$node->getConfigName()] = $config;
                $config->listInvolvedConfigs($configs);
            }
        }

        return $configs;
    }

    /**
     * @return LegacyStorage
     */
    protected function storage()
    {
        if ($this->storage === null) {
            $this->storage = LegacyStorage::getInstance();
        }

        return $this->storage;
    }

    /**
     * @param   string  $name
     * @return  Node
     * @throws  Exception
     */
    public function getNode($name)
    {
        if ($name === '__unbound__') {
            return $this->getUnboundBaseNode();
        }

        if (array_key_exists($name, $this->nodes)) {
            return $this->nodes[$name];
        }

        if ($name[0] === '@') {
            list($configName, $nodeName) = preg_split('~:\s*~', substr($name, 1), 2);
            return $this->getImportedConfig($configName)->getNode($nodeName);
        }

        // Fallback: if it is a service, create an empty one:
        $this->warn(sprintf('The node "%s" doesn\'t exist', $name));
        $pos = strpos($name, ';');
        if ($pos !== false) {
            $host = substr($name, 0, $pos);
            $service = substr($name, $pos + 1);
            // TODO: deactivated, this scares me, test it
            if ($service === 'Hoststatus') {
                return $this->createHost($host);
            } else {
                return $this->createService($host, $service);
            }
        }

        throw new Exception(
            sprintf('The node "%s" doesn\'t exist', $name)
        );
    }

    /**
     * @return BpNode
     */
    public function getUnboundBaseNode()
    {
        // Hint: state is useless here, but triggers parent/child "calculation"
        //       This is an ugly workaround and should be made obsolete
        $this->calculateAllStates();

        $names = array_keys($this->getUnboundNodes());
        $bp = new BpNode((object) array(
            'name' => '__unbound__',
            'operator' => '&',
            'child_names' => $names
        ));
        $bp->setBpConfig($this);
        $bp->setAlias($this->translate('Unbound nodes'));
        return $bp;
    }

    /**
     * @param $name
     * @return BpNode
     *
     * @throws NotFoundError
     */
    public function getBpNode($name)
    {
        if ($this->hasBpNode($name)) {
            return $this->nodes[$name];
        } else {
            throw new NotFoundError('Trying to access a missing business process node "%s"', $name);
        }
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasBpNode($name)
    {
        return array_key_exists($name, $this->nodes)
            && $this->nodes[$name] instanceof BpNode;
    }

    /**
     * Set the state for a specific node
     *
     * @param string $name  Node name
     * @param int    $state Desired state
     *
     * @return $this
     */
    public function setNodeState($name, $state)
    {
        $this->getNode($name)->setState($state);
        return $this;
    }

    /**
     * Add the given node to the given BpNode
     *
     * @param $name
     * @param BpNode $node
     *
     * @return $this
     */
    public function addNode($name, BpNode $node)
    {
        if (array_key_exists($name, $this->nodes)) {
            $this->warn(
                sprintf(
                    mt('businessprocess', 'Node "%s" has been defined twice'),
                    $name
                )
            );
        }

        $this->nodes[$name] = $node;

        if ($node->getDisplay() > 0) {
            if (! $this->isRootNode($name)) {
                $this->addRootNode($name);
            }
        } else {
            if ($this->isRootNode($name)) {
                $this->removeRootNode($name);
            }
        }


        return $this;
    }

    /**
     * Remove all occurrences of a specific node by name
     *
     * @param $name
     */
    public function removeNode($name)
    {
        unset($this->nodes[$name]);
        if (array_key_exists($name, $this->root_nodes)) {
            unset($this->root_nodes[$name]);
        }

        foreach ($this->getBpNodes() as $node) {
            if ($node->hasChild($name)) {
                $node->removeChild($name);
            }
        }
    }

    /**
     * Get all business process nodes
     *
     * @return BpNode[]
     */
    public function getBpNodes()
    {
        $nodes = array();

        foreach ($this->nodes as $node) {
            if ($node instanceof BpNode) {
                $nodes[$node->getName()] = $node;
            }
        }

        return $nodes;
    }

    /**
     * List all business process node names
     *
     * @return array
     */
    public function listBpNodes()
    {
        $nodes = array();

        foreach ($this->getBpNodes() as $name => $node) {
            $alias = $node->getAlias();
            $nodes[$name] = $name === $alias ? $name : sprintf('%s (%s)', $alias, $node);
        }

        if ($this->getMetadata()->isManuallyOrdered()) {
            uasort($nodes, function ($a, $b) {
                $a = $this->nodes[$a]->getDisplay();
                $b = $this->nodes[$b]->getDisplay();
                return $a > $b ? 1 : ($a < $b ? -1 : 0);
            });
        } else {
            natcasesort($nodes);
        }

        return $nodes;
    }

    /**
     * All business process nodes defined in this config but not
     * assigned to any parent
     *
     * @return BpNode[]
     */
    public function getUnboundNodes()
    {
        $nodes = array();

        foreach ($this->getBpNodes() as $name => $node) {
            if ($node->hasParents()) {
                continue;
            }

            if ($node->getDisplay() === 0) {
                $nodes[$name] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @return bool
     */
    public function hasWarnings()
    {
        return ! empty($this->warnings);
    }

    /**
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return ! empty($this->errors) || $this->isEmpty();
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        $errors = $this->errors;
        if ($this->isEmpty()) {
            $errors[] = sprintf(
                $this->translate(
                    'No business process nodes for "%s" have been defined yet'
                ),
                $this->getTitle()
            );
        }
        return $errors;
    }

    /**
     * Translation helper
     *
     * @param $msg
     *
     * @return mixed|string
     */
    public function translate($msg)
    {
        return mt('businessprocess', $msg);
    }

    /**
     * Add a message to our warning stack
     *
     * @param $msg
     */
    protected function warn($msg)
    {
        $args = func_get_args();
        array_shift($args);
        $this->warnings[] = vsprintf($msg, $args);
    }

    /**
     * @param string $msg,...
     *
     * @return $this
     *
     * @throws IcingaException
     */
    public function addError($msg)
    {
        $args = func_get_args();
        array_shift($args);
        if (! empty($args)) {
            $msg = vsprintf($msg, $args);
        }
        if ($this->throwErrors) {
            throw new IcingaException($msg);
        }

        $this->errors[] = $msg;
        return $this;
    }

    /**
     * Decide whether errors should be thrown or collected
     *
     * @param bool $throw
     *
     * @return $this
     */
    public function throwErrors($throw = true)
    {
        $this->throwErrors = $throw;
        return $this;
    }

    /**
     * Begin loop detection for the given name
     *
     * Will throw a NestingError in case this node will be met again below itself
     *
     * @param $name
     *
     * @throws NestingError
     */
    public function beginLoopDetection($name)
    {
        // echo "Begin loop $name\n";
        if (array_key_exists($name, $this->loopDetection)) {
            $loop = array_keys($this->loopDetection);
            $loop[] = $name;
            $this->loopDetection = array();
            throw new NestingError('Loop detected: %s', implode(' -> ', $loop));
        }

        $this->loopDetection[$name] = true;
    }

    /**
     * Remove the given name from the loop detection stack
     *
     * @param $name
     */
    public function endLoopDetection($name)
    {
        // echo "End loop $this->name\n";
        unset($this->loopDetection[$name]);
    }

    /**
     * Whether this configuration has any Nodes
     *
     * @return bool
     */
    public function isEmpty()
    {
        // This is faster
        if (! empty($this->root_nodes)) {
            return false;
        }

        return count($this->listBpNodes()) === 0;
    }

    /**
     * Export the config to array
     *
     * @param   bool    $flat   If false, children will be added to the array key children, else the array will be flat
     *
     * @return  array
     */
    public function toArray($flat = false)
    {
        $data = [
            'name' => $this->getTitle(),
            'path' => $this->getTitle()
        ];

        $children = [];

        foreach ($this->getChildren() as $node) {
            if ($flat) {
                $children = array_merge($children, $node->toArray($data, $flat));
            } else {
                $children[] = $node->toArray($data, $flat);
            }
        }

        if ($flat) {
            $data = [$data];

            if (! empty($children)) {
                $data = array_merge($data, $children);
            }
        } else {
            $data['children'] = $children;
        }

        return $data;
    }
}
