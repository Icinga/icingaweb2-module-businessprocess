<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Businessprocess\Html\Link;

abstract class Node
{
    const FLAG_DOWNTIME = 1;
    const FLAG_ACK      = 2;
    const FLAG_MISSING  = 4;
    const FLAG_NONE     = 8;
    const SHIFT_FLAGS   = 4;

    const ICINGA_OK          = 0;
    const ICINGA_WARNING     = 1;
    const ICINGA_CRITICAL    = 2;
    const ICINGA_UNKNOWN     = 3;
    const ICINGA_UP          = 0;
    const ICINGA_DOWN        = 1;
    const ICINGA_UNREACHABLE = 2;
    const ICINGA_PENDING     = 99;

    protected static $sortStateToStateMap = array(
        4 => self::ICINGA_CRITICAL,
        3 => self::ICINGA_UNKNOWN,
        2 => self::ICINGA_WARNING,
        1 => self::ICINGA_PENDING,
        0 => self::ICINGA_OK
    );

    protected static $stateToSortStateMap = array(
        self::ICINGA_PENDING  => 1,
        self::ICINGA_UNKNOWN  => 3,
        self::ICINGA_CRITICAL => 4,
        self::ICINGA_WARNING  => 2,
        self::ICINGA_OK       => 0,
    );

    /**
     * Main business process object
     *
     * @var BusinessProcess
     */
    protected $bp;

    /**
     * Parent nodes
     *
     * @var array
     */
    protected $parents = array();

    /**
     * Node identifier
     *
     * @var string
     */
    protected $name;

    /**
     * Node state
     *
     * @var int
     */
    protected $state;

    /**
     * Whether this nodes state has been acknowledged
     *
     * @var bool
     */
    protected $ack;

    /**
     * Whether this node is in a scheduled downtime
     *
     * @var bool
     */
    protected $downtime;

    // obsolete
    protected $duration;

    /**
     * Last state change, unix timestamp
     *
     * @var int
     */
    protected $lastStateChange;

    protected $missing = false;

    protected $className = 'unknown';

    protected static $state_names = array(
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
        99 => 'PENDING'
    );

    abstract public function __construct(BusinessProcess $bp, $object);

    public function setMissing($missing = true)
    {
        $this->missing = $missing;
        return $this;
    }

    public function isProblem()
    {
        return $this->getState() > 0;
    }

    public function hasBeenChanged()
    {
        return false;
    }

    public function isMissing()
    {
        return $this->missing;
    }

    public function hasMissingChildren()
    {
        return count($this->getMissingChildren()) > 0;
    }

    public function getMissingChildren()
    {
        return array();
    }

    public function hasInfoUrl()
    {
        return false;
    }

    public function setState($state)
    {
        $this->state = (int) $state;
        return $this;
    }

    /**
     * Forget my state
     *
     * @return $this
     */
    public function clearState()
    {
        $this->state = null;
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

    public function getStateName($state = null)
    {
        if ($state === null) {
            return static::$state_names[ $this->getState() ];
        } else {
            return static::$state_names[ $state ];
        }
    }

    public function enumStateNames()
    {
        return static::$state_names;
    }

    public function getState()
    {
        if ($this->state === null) {
            throw new ProgrammingError(
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
        $sort = $this->stateToSortState($this->getState());
        $sort = ($sort << self::SHIFT_FLAGS)
               + ($this->isInDowntime() ? self::FLAG_DOWNTIME : 0)
               + ($this->isAcknowledged() ? self::FLAG_ACK : 0);
        if (! ($sort & (self::FLAG_DOWNTIME | self::FLAG_ACK))) {
            $sort |= self::FLAG_NONE;
        }
        return $sort;
    }

    public function getLastStateChange()
    {
        return $this->lastStateChange;
    }

    public function setLastStateChange($timestamp)
    {
        $this->lastStateChange = $timestamp;
        return $this;
    }

    public function addParent(Node $parent)
    {
        $this->parents[] = $parent;
        return $this;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function isHandled()
    {
        return $this->isInDowntime() || $this->isAcknowledged();
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

    public function getChildren($filter = null)
    {
        return array();
    }

    public function countChildren($filter = null)
    {
        return count($this->getChildren($filter));
    }

    public function hasChildren($filter = null)
    {
        return $this->countChildren($filter) > 0;
    }

    public function isEmpty()
    {
        return $this->countChildren() === 0;
    }

    public function hasAlias()
    {
        return false;
    }

    public function getAlias()
    {
        return $this->name;
    }

    public function hasParents()
    {
        return count($this->parents) > 0;
    }

    public function getParents()
    {
        return $this->parents;
    }

    protected function stateToSortState($state)
    {
        if (array_key_exists($state, static::$stateToSortStateMap)) {
            return static::$stateToSortStateMap[$state];
        }

        throw new ProgrammingError('Got invalid state: %s',  var_export($state, 1));
    }

    protected function sortStateTostate($sortState)
    {
        $sortState = $sortState >> self::SHIFT_FLAGS;

        if (array_key_exists($sortState, static::$sortStateToStateMap)) {
            return static::$sortStateToStateMap[$sortState];
        }

        throw new ProgrammingError('Got invalid sorting state %s', $sortState);
    }

    public function getObjectClassName()
    {
        return $this->className;
    }

    /**
     * @return Link
     */
    public function getLink()
    {
        return Link::create($this->getAlias(), '#');
    }

    public function operatorHtml()
    {
        return '&nbsp;';
    }

    // TODO: Why isn't this abstract?
    // abstract public function toLegacyConfigString();
    public function toLegacyConfigString(& $rendered = array())
    {
        return '';
    }

    public function getName()
    {
        return $this->name;
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function __destruct()
    {
        unset($this->parents);
    }
}
