<?php

namespace Icinga\Module\Bpapp;

use Exception;

abstract class Node
{
    const FLAG_DOWNTIME = 1;
    const FLAG_ACK      = 2;
    const FLAG_MISSING  = 4;
    const FLAG_NONE     = 8;
    const SHIFT_FLAGS   = 4;

    protected $bp;
    protected $parent;
    protected $name;
    protected $state;
    protected $description;
    # protected $flags = 0;
    protected $ack;
    protected $downtime;
    protected $recent_problems = array();
    protected $duration;
    protected $missing = false;

    protected static $state_names = array(
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN'
    );

    abstract public function __construct(BusinessProcess $bp, $object);

    public function setMissing($missing = true)
    {
        $this->missing = $missing;
        return $this;
    }

    public function isMissing()
    {
        return $this->missing;
    }

    public function addChild(Node $node)
    {
        if (array_key_exists((string) $node, $this->children)) {
            throw new Exception(
                sprintf(
                    'Node "%s" has been defined more than once',
                    $node
                )
            );
        }
        $this->childs[(string) $node] = $node;
        $node->setParent($this);
        return $this;
    }

    public function setState($state)
    {
        $this->state = (int) $state;
        return $this;
    }

    public function setAck($ack = true)
    {
        $this->ack = $ack;
        return $this;
    }

    public function setDowntime($downtime = true)
    {
        $this->downtime = $downtime;
        return $this;
    }

    public function getStateName()
    {
        return self::$state_names[ $this->getState() ];
    }

    public function getState()
    {
        if ($this->state === null) {
            throw new Exception(
                sprintf(
                    'Node %s is unable to retrieve it\'s state',
                    $this->name
                )
            );
        }
        return $this->state;
    }

    public function getSortingState()
    {
        $state = $this->getState();
        if ($state === 3) {
            $state = 2;
        } elseif ($state === 2) {
            $state = 3;
        }
        $state = ($state << self::SHIFT_FLAGS)
               + ($this->isInDowntime() ? self::FLAG_DOWNTIME : 0)
               + ($this->isAcknowledged() ? self::FLAG_ACK : 0);
        if (! ($state & (self::FLAG_DOWNTIME | self::FLAG_ACK))) {
            $state |= self::FLAG_NONE;
        }
        return $state;
    }

    public function setParent(Node $parent)
    {
        $this->parent = $parent;
        return $this;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function isInDowntime()
    {
        if ($this->downtime === null) {
            $this->getState();
        }
        return $this->downtime;
    }

    public function isAcknowledged()
    {
        if ($this->ack === null) {
            $this->getState();
        }
        return $this->ack;
    }

    public function hasChildren()
    {
        return false;
    }

    public function __destruct()
    {
        // required to avoid memleeks in PHP < 5.3:
        $this->parent = null;
        $this->children = array();
    }

    public function __toString()
    {
        return $this->name;
    }
}
