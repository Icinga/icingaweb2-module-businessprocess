<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Exception\ConfigurationError;
use Icinga\Exception\ProgrammingError;

class BpNode extends Node
{
    const OP_AND = '&';
    const OP_OR  = '|';
    protected $operator = '&';
    protected $url;
    protected $info_command;
    protected $display = 0;
    protected $children;
    protected $child_names = array();
    protected $alias;
    protected $counters;
    protected $missing = null;

    );

    protected $className = 'process';

    public function __construct(BusinessProcess $bp, $object)
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
            $this->counters = array(0, 0, 0, 0, 99 => 0);
            foreach ($this->children as $child) {
                if ($child instanceof BpNode) {
                    $counters = $child->getStateSummary();
                    foreach ($counters as $k => $v) {
                        $this->counters[$k] += $v;
                    }
                } else {
                    $state = $child->getState();
                    $this->counters[$state]++;
                }
            }
        }
        return $this->counters;
    }

    public function isMissing()
    {
        if ($this->missing === null) {
            $exists = false;
            foreach ($this->getChildren() as $child) {
                if (! $child->isMissing()) {
                    $exists = true;
                }
            }
            $this->missing = ! $exists;
        }
        return $this->missing;
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
        return $this->url !== null;
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

    public function getState()
    {
        if ($this->state === null) {
            $this->calculateState();
        }
        return $this->state;
    }

    protected function calculateState()
    {
        $sort_states = array();
        $lastStateChange = 0;
        foreach ($this->getChildren() as $child) {
            $sort_states[] = $child->getSortingState();
            $lastStateChange = max($lastStateChange, $child->getLastStateChange());
        }
        $this->setLastStateChange($lastStateChange);

        switch ($this->operator) {
            case self::OP_AND:
                $sort_state = max($sort_states);
                break;
            case self::OP_OR:
                $sort_state = min($sort_states);
                break;
            default:
                // MIN:
                sort($sort_states);

                // default -> unknown
                $sort_state = 3 << self::SHIFT_FLAGS;

                for ($i = 1; $i <= $this->operator; $i++) {
                    $sort_state = array_shift($sort_states);
                }
        }
        if ($sort_state & self::FLAG_DOWNTIME) {
            $this->setDowntime(true);
        }
        if ($sort_state & self::FLAG_ACK) {
            $this->setAck(true);
        }

        $this->state = $this->sortStateTostate($sort_state);
    }

    public function countChildren()
    {
        return count($this->getChildren());
    }

    public function hasChildren()
    {
        return $this->countChildren() > 0;
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
        sort($names);
        $this->child_names = $names;
        $this->children = null;
        return $this;
    }

    public function getChildNames()
    {
        return $this->child_names;
    }

    public function getChildren()
    {
        if ($this->children === null) {
            $this->children = array();
            natsort($this->child_names);
            foreach ($this->child_names as $name) {
                $this->children[$name] = $this->bp->getNode($name);
                $this->children[$name]->addParent($this);
            }
        }
        return $this->children;
    }

    protected function assertNumericOperator()
    {
        if (! is_numeric($this->operator)) {
            throw new ConfigurationError('Got invalid operator: %s', $this->operator);
        }
    }

    protected function getActionIcons($view)
    {
        $icons = array();
        if (! $this->bp->isLocked() && $this->name !== '__unbound__') {
            $icons[] = $this->actionIcon(
                $view,
                'wrench',
                $view->url('businessprocess/node/edit', array(
                    'config' => $this->bp->getName(),
                    'node'   => $this->name
                )),
                mt('businessprocess', 'Modify this node')
            );
        }
        return $icons;
    }

    public function toLegacyConfigString(& $rendered = array())
    {
        $cfg = '';
        if (array_key_exists($this->name, $rendered)) {
            return $cfg;
        }
        $rendered[$this->name] = true;
        $children = array();
        
        foreach ($this->getChildren() as $name => $child) {
            $children[] = (string) $child;
            if (array_key_exists($name, $rendered)) { continue; }
            if ($child instanceof BpNode) {
                $cfg .= $child->toLegacyConfigString($rendered) . "\n";
            }
        }
        $eq = '=';
        $op = $this->operator;
        if (is_numeric($op)) {
            $eq = '= ' . $op . ' of:';
            $op = '+';
        }

        $strChildren = implode(' ' . $op . ' ', $children);
        if ((count($children) < 2) && $op !== '&') {
            $strChildren = $op . ' ' . $strChildren;
        }
        $cfg .= sprintf(
            "%s %s %s\n",
            $this->name,
            $eq,
            $strChildren
        );
        if ($this->hasAlias() || $this->getDisplay() > 0) {
            $prio = $this->getDisplay();
            $cfg .= sprintf(
                "display %s;%s;%s\n",
                $prio,
                $this->name,
                $this->getAlias()
            );
        }
        if ($this->hasInfoUrl()) {
            $cfg .= sprintf(
                "info_url;%s;%s\n",
                $this->name,
                $this->getInfoUrl()
            );
        }

        return $cfg;
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
            default:
                // MIN
                $this->assertNumericOperator();
                return 'min:' . $this->operator;
        }
    }
}
