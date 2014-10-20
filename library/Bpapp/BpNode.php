<?php

namespace Icinga\Module\Bpapp;

use Exception;

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

    public function __construct(
        BusinessProcess $bp,
        $object
/*
        $name,
        $operator,
        $child_names
*/
    ) {
        $this->bp = $bp;
        $this->name = $object->name;
        $this->operator = $object->operator;
        $this->setChildNames($object->child_names);
    }

    public function getStateSummary()
    {
        if ($this->counters === null) {
            $this->getState();
            $this->counters = array(0, 0, 0, 0);
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

    public function getOperator()
    {
        return $this->operator;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function hasUrl()
    {
        return $this->url !== null;
    }

    public function setInfoCommand($cmd)
    {
        $this->info_command = $cmd;
    }

    public function hasInfoCommand()
    {
        return $this->info_command !== null;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getInfoCommand()
    {
        return $this->info_command;
    }

    public function getAlias()
    {
        return $this->alias;
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
        foreach ($this->getChildren() as $child) {
            $sort_states[] = $child->getSortingState();
        }
        switch ($this->operator) {
            case self::OP_AND:
                $state = max($sort_states);
                break;
            case self::OP_OR:
                $state = min($sort_states);
                break;
            default:
                // MIN:
                if (! is_numeric($this->operator)) {
                    throw new Exception(
                        sprintf(
                            'Got invalid operator: %s',
                            $this->operator
                        )
                    );
                }
                sort($sort_states);

                // default -> unknown
                $state = 2 << self::SHIFT_FLAGS;

                for ($i = 1; $i <= $this->operator; $i++) {
                    $state = array_shift($sort_states);
                }
        }
        if ($state & self::FLAG_DOWNTIME) {
            $this->setDowntime(true);
        }
        if ($state & self::FLAG_ACK) {
            $this->setAck(true);
        }
        $state = $state >> self::SHIFT_FLAGS;

        if ($state === 3) {
            $this->state = 2;
        } elseif ($state === 2) {
            $this->state = 3;
        } else {
            $this->state = $state;
        }
    }

    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    public function setDisplay($display)
    {
        $this->display = $display;
        return $this;
    }

    public function setChildNames($names)
    {
        $this->child_names = $names;
        $this->children = null;
        return $this;
    }

    public function getChildren()
    {
        if ($this->children === null) {
            $this->children = array();
            natsort($this->child_names);
            foreach ($this->child_names as $name) {
                $this->children[$name] = $this->bp->getNode($name);
            }
        }
        return $this->children;
    }
}
