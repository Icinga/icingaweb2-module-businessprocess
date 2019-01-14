<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\NotFoundError;
use Icinga\Module\Businessprocess\Exception\NestingError;

class BpNode extends Node
{
    const OP_AND = '&';
    const OP_OR  = '|';
    const OP_NOT  = '!';
    protected $operator = '&';
    protected $url;
    protected $info_command;
    protected $display = 0;

    /** @var  Node[] */
    protected $children;

    /** @var array */
    protected $childNames = array();
    protected $alias;
    protected $counters;
    protected $missing = null;
    protected $missingChildren;

    protected static $emptyStateSummary = array(
        'OK'          => 0,
        'WARNING'     => 0,
        'CRITICAL'    => 0,
        'UNKNOWN'     => 0,
        'PENDING'     => 0,
        'UP'          => 0,
        'DOWN'        => 0,
        'UNREACHABLE' => 0,
        'MISSING'     => 0,
    );

    protected static $sortStateInversionMap = array(
        4 => 0,
        3 => 0,
        2 => 2,
        1 => 1,
        0 => 4
    );

    protected $className = 'process';

    public function __construct(BpConfig $bp, $object)
    {
        $this->bp = $bp;
        $this->name = $object->name;
        $this->setOperator($object->operator);
        $this->setChildNames($object->child_names);
    }

    public function getStateSummary()
    {
        if ($this->counters === null) {
            $this->getState();
            $this->counters = self::$emptyStateSummary;

            foreach ($this->getChildren() as $child) {
                if ($child instanceof BpNode) {
                    $counters = $child->getStateSummary();
                    foreach ($counters as $k => $v) {
                        $this->counters[$k] += $v;
                    }
                } elseif ($child->isMissing()) {
                    $this->counters['MISSING']++;
                } else {
                    $state = $child->getStateName();
                    $this->counters[$state]++;
                }
            }
            if (! $this->hasChildren()) {
                $this->counters['MISSING']++;
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
            if ($child->isProblem()
                || ($child instanceof BpNode && $child->hasProblems())
            ) {
                $problems[] = $child;
            }
        }

        return $problems;
    }

    public function hasChild($name)
    {
        return in_array($name, $this->childNames);
    }

    public function removeChild($name)
    {
        if (($key = array_search($name, $this->childNames)) !== false) {
            unset($this->childNames[$key]);

            if (! empty($this->children)) {
                unset($this->children[$name]);
            }
        }

        return $this;
    }

    public function getProblemTree()
    {
        $tree = array();

        foreach ($this->getProblematicChildren() as $child) {
            $name = (string) $child;
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

    public function isMissing()
    {
        if ($this->missing === null) {
            $exists = false;
            $bp = $this->bp;
            $bp->beginLoopDetection($this->name);
            foreach ($this->getChildren() as $child) {
                if (! $child->isMissing()) {
                    $exists = true;
                }
            }
            $bp->endLoopDetection($this->name);
            $this->missing = ! $exists;
        }
        return $this->missing;
    }

    public function getMissingChildren()
    {
        if ($this->missingChildren === null) {
            $missing = array();

            foreach ($this->getChildren() as $child) {
                if ($child->isMissing()) {
                    $missing[(string) $child] = $child;
                }

                foreach ($child->getMissingChildren() as $m) {
                    $missing[(string) $m] = $m;
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
            case self::OP_NOT:
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

    public function setInfoCommand($cmd)
    {
        $this->info_command = $cmd;
    }

    public function hasInfoCommand()
    {
        return $this->info_command !== null;
    }

    public function getInfoCommand()
    {
        return $this->info_command;
    }

    public function hasAlias()
    {
        return $this->alias !== null;
    }

    public function getAlias()
    {
        return $this->alias ? preg_replace('~_~', ' ', $this->alias) : $this->name;
    }

    public function setAlias($name)
    {
        $this->alias = $name;
        return $this;
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
                $this->bp->addError(
                    $this->bp->translate('Nesting error detected: %s'),
                    $e->getMessage()
                );

                // Failing nodes are unknown
                $this->state = 3;
            }
        }

        return $this->state;
    }

    public function getHtmlId()
    {
        return 'businessprocess-' . preg_replace('/[\r\n\t\s]/', '_', (string) $this);
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
        $bp = $this->bp;

        $sort_states = array();
        $lastStateChange = 0;

        if (!$this->hasChildren()) {
            // TODO: delegate this to operators, should mostly fail
            $this->setState(self::ICINGA_UNKNOWN);
            $this->setMissing();
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
            $sort_states[] = $child->getSortingState();
            $lastStateChange = max($lastStateChange, $child->getLastStateChange());
            $bp->endLoopDetection($this->name);
        }

        $this->setLastStateChange($lastStateChange);

        switch ($this->operator) {
            case self::OP_AND:
                $sort_state = max($sort_states);
                break;
            case self::OP_NOT:
                $sort_state = $this->invertSortingState(max($sort_states));
                break;
            case self::OP_OR:
                $sort_state = min($sort_states);
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
        $bp = $this->bp;
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
        if (! $this->bp->getMetadata()->isManuallyOrdered()) {
            natcasesort($names);
            $names = array_values($names);
        }

        $this->childNames = $names;
        $this->children = null;
        return $this;
    }

    public function hasChildren($filter = null)
    {
        return !empty($this->childNames);
    }

    public function getChildNames()
    {
        return $this->childNames;
    }

    public function getChildren($filter = null)
    {
        if ($this->children === null) {
            $this->children = array();
            if (! $this->bp->getMetadata()->isManuallyOrdered()) {
                natcasesort($this->childNames);
                $this->childNames = array_values($this->childNames);
            }
            foreach ($this->childNames as $name) {
                $this->children[$name] = $this->bp->getNode($name);
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
        if (! is_numeric($this->operator)) {
            throw new ConfigurationError('Got invalid operator: %s', $this->operator);
        }
    }

    public function operatorHtml()
    {
        switch ($this->operator) {
            case self::OP_AND:
                return 'and';
                break;
            case self::OP_OR:
                return 'or';
                break;
            case self::OP_NOT:
                return 'not';
                break;
            default:
                // MIN
                $this->assertNumericOperator();
                return 'min:' . $this->operator;
        }
    }
}
