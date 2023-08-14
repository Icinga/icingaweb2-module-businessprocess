<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Businessprocess\Exception\NestingError;
use ipl\Web\Widget\Icon;

class BpNode extends Node
{
    const OP_AND = '&';
    const OP_OR  = '|';
    const OP_XOR  = '^';
    const OP_NOT  = '!';
    const OP_DEGRADED  = '%';

    protected $operator = '&';

    protected $url;

    protected $display = 0;

    /** @var  ?Node[] */
    protected $children;

    /** @var array */
    protected $childNames = array();
    protected $counters;
    protected $missing = null;
    protected $empty = null;
    protected $missingChildren;
    protected $stateOverrides = [];

    protected static $emptyStateSummary = array(
        'CRITICAL'            => 0,
        'CRITICAL-HANDLED'    => 0,
        'WARNING'             => 0,
        'WARNING-HANDLED'     => 0,
        'UNKNOWN'             => 0,
        'UNKNOWN-HANDLED'     => 0,
        'OK'                  => 0,
        'PENDING'             => 0,
        'MISSING'             => 0,
        'EMPTY'               => 0,
    );

    protected static $sortStateInversionMap = array(
        4 => 0,
        3 => 0,
        2 => 2,
        1 => 1,
        0 => 4
    );

    protected $className = 'process';

    public function __construct($object)
    {
        $this->name = BpConfig::escapeName($object->name);
        $this->alias = BpConfig::unescapeName($object->name);
        $this->operator = $object->operator;
        $this->childNames = $object->child_names;
    }

    public function getStateSummary()
    {
        if ($this->counters === null) {
            $this->getState();
            $this->counters = self::$emptyStateSummary;

            foreach ($this->getChildren() as $child) {
                if ($child->isMissing()) {
                    $this->counters['MISSING']++;
                } else {
                    $state = $child->getStateName($this->getChildState($child));
                    if ($child->isHandled() && ($state !== 'UP' && $state !== 'OK')) {
                        $state = $state . '-HANDLED';
                    }

                    if ($state === 'DOWN') {
                        $this->counters['CRITICAL']++;
                    } elseif ($state === 'DOWN-HANDLED') {
                        $this->counters['CRITICAL-HANDLED']++;
                    } elseif ($state === 'UNREACHABLE') {
                        $this->counters['UNKNOWN']++;
                    } elseif ($state === 'UNREACHABLE-HANDLED') {
                        $this->counters['UNKNOWN-HANDLED']++;
                    } elseif ($state === 'PENDING-HANDLED') {
                        $this->counters['PENDING']++;
                    } elseif ($state === 'UP') {
                        $this->counters['OK']++;
                    } else {
                        $this->counters[$state]++;
                    }
                }
            }
        }
        return $this->counters;
    }

    public function hasProblems()
    {
        if ($this->isProblem()) {
            return true;
        }

        $okStates = array('OK', 'UP', 'PENDING', 'MISSING');

        foreach ($this->getStateSummary() as $state => $cnt) {
            if ($cnt !== 0 && ! in_array($state, $okStates)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Node $node
     * @return $this
     * @throws ConfigurationError
     */
    public function addChild(Node $node)
    {
        if ($this->children === null) {
            $this->getChildren();
        }

        $name = $node->getName();
        if (array_key_exists($name, $this->children)) {
            throw new ConfigurationError(
                'Node "%s" has been defined more than once',
                $name
            );
        }

        $this->children[$name] = $node;
        $this->childNames[] = $name;
        $node->addParent($this);
        return $this;
    }

    public function getProblematicChildren()
    {
        $problems = array();

        foreach ($this->getChildren() as $child) {
            if (isset($this->stateOverrides[$child->getName()])) {
                $problem = $this->getChildState($child) > 0;
            } else {
                $problem = $child->isProblem() || ($child instanceof BpNode && $child->hasProblems());
            }

            if ($problem) {
                $problems[] = $child;
            }
        }

        return $problems;
    }

    public function hasChild($name)
    {
        return in_array($name, $this->getChildNames());
    }

    public function removeChild($name)
    {
        if (($key = array_search($name, $this->getChildNames())) !== false) {
            unset($this->childNames[$key]);

            if (! empty($this->children)) {
                unset($this->children[$name]);
            }

            $this->childNames = array_values($this->childNames);
        }

        return $this;
    }

    public function getProblemTree()
    {
        $tree = array();

        foreach ($this->getProblematicChildren() as $child) {
            $name = $child->getName();
            $tree[$name] = array(
                'node'     => $child,
                'children' => array()
            );
            if ($child instanceof BpNode) {
                $tree[$name]['children'] = $child->getProblemTree();
            }
        }

        return $tree;
    }

    /**
     * Get the problem nodes as tree reduced to the nodes which have the same state as the business process
     *
     * @param bool $rootCause Reduce nodes to the nodes which are responsible for the state of the business process
     *
     * @return array
     */
    public function getProblemTreeBlame($rootCause = false)
    {
        $tree = [];
        $nodeState = $this->getState();

        if ($nodeState !== 0) {
            foreach ($this->getChildren() as $child) {
                $childState = $this->getChildState($child);
                $childState = $rootCause ? $child->getSortingState($childState) : $childState;
                if (($rootCause ? $this->getSortingState() : $nodeState) === $childState) {
                    $name = $child->getName();
                    $tree[$name] = [
                        'children' => [],
                        'node'     => $child
                    ];
                    if ($child instanceof BpNode) {
                        $tree[$name]['children'] = $child->getProblemTreeBlame($rootCause);
                    }
                }
            }
        }

        return $tree;
    }


    public function isMissing()
    {
        if ($this->missing === null) {
            $exists = false;
            $bp = $this->getBpConfig();
            $bp->beginLoopDetection($this->name);
            foreach ($this->getChildren() as $child) {
                if (! $child->isMissing()) {
                    $exists = true;
                }
            }
            $bp->endLoopDetection($this->name);
            $this->missing = ! $exists && ! empty($this->getChildren());
        }
        return $this->missing;
    }

    public function isEmpty()
    {
        $bp = $this->getBpConfig();
        $empty = true;
        if ($this->countChildren()) {
            $bp->beginLoopDetection($this->name);
            foreach ($this->getChildren() as $child) {
                if ($child instanceof MonitoredNode) {
                    $empty = false;
                    break;
                } elseif (!$child->isEmpty()) {
                    $empty = false;
                }
            }
            $bp->endLoopDetection($this->name);
        }
        $this->empty = $empty;

        return $this->empty;
    }


    public function getMissingChildren()
    {
        if ($this->missingChildren === null) {
            $missing = array();

            foreach ($this->getChildren() as $child) {
                if ($child->isMissing()) {
                    $missing[$child->getAlias() ?? $child->getName()] = $child;
                }

                foreach ($child->getMissingChildren() as $m) {
                    $missing[$m->getAlias() ?? $m->getName()] = $m;
                }
            }

            $this->missingChildren = $missing;
        }

        return $this->missingChildren;
    }

    public function getOperator()
    {
        return $this->operator;
    }

    public function setOperator($operator)
    {
        $this->assertValidOperator($operator);
        $this->operator = $operator;
        return $this;
    }

    protected function assertValidOperator($operator)
    {
        switch ($operator) {
            case self::OP_AND:
            case self::OP_OR:
            case self::OP_XOR:
            case self::OP_NOT:
            case self::OP_DEGRADED:
                return;
            default:
                if (is_numeric($operator)) {
                    return;
                }
        }

        throw new ConfigurationError(
            'Got invalid operator: %s',
            $operator
        );
    }

    public function setInfoUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    public function hasInfoUrl()
    {
        return ! empty($this->url);
    }

    public function getInfoUrl()
    {
        return $this->url;
    }

    public function setStateOverrides(array $overrides, $name = null)
    {
        if ($name === null) {
            $this->stateOverrides = $overrides;
        } else {
            $this->stateOverrides[$name] = $overrides;
        }

        return $this;
    }

    public function getStateOverrides($name = null)
    {
        $overrides = null;
        if ($name !== null) {
            if (isset($this->stateOverrides[$name])) {
                $overrides = $this->stateOverrides[$name];
            }
        } else {
            $overrides = $this->stateOverrides;
        }

        return $overrides;
    }

    public function getAlias()
    {
        return $this->alias ? preg_replace('~_~', ' ', $this->alias) : $this->name;
    }

    /**
     * @return int
     */
    public function getState()
    {
        if ($this->state === null) {
            try {
                $this->reCalculateState();
            } catch (NestingError $e) {
                $this->getBpConfig()->addError(
                    $this->getBpConfig()->translate('Nesting error detected: %s'),
                    $e->getMessage()
                );

                // Failing nodes are unknown
                $this->state = 3;
            }
        }

        return $this->state;
    }

    /**
     * Get the given child's state, possibly adjusted by override rules
     *
     * @param Node|string $child
     * @return int
     */
    public function getChildState($child)
    {
        if (! $child instanceof Node) {
            $child = $this->getChildByName($child);
        }

        $childName = $child->getName();
        $childState = $child->getState();
        if (! isset($this->stateOverrides[$childName][$childState])) {
            return $childState;
        }

        return $this->stateOverrides[$childName][$childState];
    }

    public function getHtmlId()
    {
        return 'businessprocess-' . preg_replace('/[\r\n\t\s]/', '_', $this->getName());
    }

    protected function invertSortingState($state)
    {
        return self::$sortStateInversionMap[$state >> self::SHIFT_FLAGS] << self::SHIFT_FLAGS;
    }

    /**
     * @return $this
     */
    public function reCalculateState()
    {
        $bp = $this->getBpConfig();

        $sort_states = array();
        $lastStateChange = 0;

        if ($this->isEmpty()) {
            // TODO: delegate this to operators, should mostly fail
            $this->setState(self::NODE_EMPTY);
            return $this;
        }

        foreach ($this->getChildren() as $child) {
            $bp->beginLoopDetection($this->name);
            if ($child instanceof MonitoredNode && $child->isMissing()) {
                if ($child instanceof HostNode) {
                    $child->setState(self::ICINGA_UNREACHABLE);
                } else {
                    $child->setState(self::ICINGA_UNKNOWN);
                }

                $child->setMissing();
            }
            $sort_states[] = $child->getSortingState($this->getChildState($child));
            $lastStateChange = max($lastStateChange, $child->getLastStateChange());
            $bp->endLoopDetection($this->name);
        }

        $this->setLastStateChange($lastStateChange);

        switch ($this->getOperator()) {
            case self::OP_AND:
                $sort_state = max($sort_states);
                break;
            case self::OP_NOT:
                $sort_state = $this->invertSortingState(max($sort_states));
                break;
            case self::OP_OR:
                $sort_state = min($sort_states);
                break;
            case self::OP_XOR:
                $actualGood = 0;
                foreach ($sort_states as $s) {
                    if ($this->sortStateTostate($s) === self::ICINGA_OK) {
                        $actualGood++;
                    }
                }

                if ($actualGood === 1) {
                    $this->state = self::ICINGA_OK;
                } else {
                    $this->state = self::ICINGA_CRITICAL;
                }

                return $this;
            case self::OP_DEGRADED:
                $maxState = max($sort_states);
                $flags = $maxState & 0xf;

                $maxIcingaState = $this->sortStateTostate($maxState);
                $warningState = ($this->stateToSortState(self::ICINGA_WARNING) << self::SHIFT_FLAGS) + $flags;

                $sort_state = ($maxIcingaState === self::ICINGA_CRITICAL) ? $warningState : $maxState;
                break;
            default:
                // MIN:
                $sort_state = 3 << self::SHIFT_FLAGS;

                if (count($sort_states) >= $this->operator) {
                    $actualGood = 0;
                    foreach ($sort_states as $s) {
                        if (($s >> self::SHIFT_FLAGS) === self::ICINGA_OK) {
                            $actualGood++;
                        }
                    }

                    if ($actualGood >= $this->operator) {
                        // condition is fulfilled
                        $sort_state = self::ICINGA_OK;
                    } else {
                        // worst state if not fulfilled
                        $sort_state = max($sort_states);
                    }
                }
        }
        if ($sort_state & self::FLAG_DOWNTIME) {
            $this->setDowntime(true);
        }
        if ($sort_state & self::FLAG_ACK) {
            $this->setAck(true);
        }

        $this->state = $this->sortStateTostate($sort_state);
        return $this;
    }

    public function checkForLoops()
    {
        $bp = $this->getBpConfig();
        foreach ($this->getChildren() as $child) {
            $bp->beginLoopDetection($this->name);
            if ($child instanceof BpNode) {
                $child->checkForLoops();
            }
            $bp->endLoopDetection($this->name);
        }

        return $this;
    }

    public function setDisplay($display)
    {
        $this->display = (int) $display;
        return $this;
    }

    public function getDisplay()
    {
        return $this->display;
    }

    public function setChildNames($names)
    {
        $this->childNames = $names;
        $this->children = null;
        return $this;
    }

    public function hasChildren($filter = null)
    {
        $childNames = $this->getChildNames();
        return !empty($childNames);
    }

    public function getChildNames()
    {
        return $this->childNames;
    }

    public function getChildren($filter = null)
    {
        if ($this->children === null) {
            $this->children = [];
            foreach ($this->getChildNames() as $name) {
                $this->children[$name] = $this->getBpConfig()->getNode($name);
                $this->children[$name]->addParent($this);
            }
        }

        return $this->children;
    }

    /**
     * return BpNode[]
     */
    public function getChildBpNodes()
    {
        $children = array();

        foreach ($this->getChildren() as $name => $child) {
            if ($child instanceof BpNode) {
                $children[$name] = $child;
            }
        }

        return $children;
    }

    /**
     * @param $childName
     * @return Node
     * @throws NotFoundError
     */
    public function getChildByName($childName)
    {
        foreach ($this->getChildren() as $name => $child) {
            if ($name === $childName) {
                return $child;
            }
        }

        throw new NotFoundError('Trying to get missing child %s', $childName);
    }

    protected function assertNumericOperator()
    {
        if (! is_numeric($this->getOperator())) {
            throw new ConfigurationError('Got invalid operator: %s', $this->operator);
        }
    }

    public function operatorHtml()
    {
        switch ($this->getOperator()) {
            case self::OP_AND:
                return 'AND';
                break;
            case self::OP_OR:
                return 'OR';
            case self::OP_XOR:
                return 'XOR';
                break;
            case self::OP_NOT:
                return 'NOT';
                break;
            case self::OP_DEGRADED:
                return 'DEG';
                break;
            default:
                // MIN
                $this->assertNumericOperator();
                return 'min:' . $this->operator;
        }
    }

    public function getIcon(): Icon
    {
        $this->icon = $this->hasParents() ? 'cubes' : 'sitemap';
        return parent::getIcon();
    }
}
