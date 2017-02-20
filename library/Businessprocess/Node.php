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

    protected $sortStateToStateMap = array(
        4 => self::ICINGA_CRITICAL,
        3 => self::ICINGA_UNKNOWN,
        2 => self::ICINGA_WARNING,
        1 => self::ICINGA_PENDING,
        0 => self::ICINGA_OK
    );

    protected $stateToSortStateMap = array(
        self::ICINGA_PENDING  => 1,
        self::ICINGA_UNKNOWN  => 3,
        self::ICINGA_CRITICAL => 4,
        self::ICINGA_WARNING  => 2,
        self::ICINGA_OK       => 0,
    );

    /**
     * Main business process object
     *
     * @var BpConfig
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

    protected $stateNames = array(
        'OK',
        'WARNING',
        'CRITICAL',
        'UNKNOWN',
        99 => 'PENDING'
    );

    abstract public function __construct(BpConfig $bp, $object);

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
        $this->missing = false;
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
        $states = $this->enumStateNames();
        if ($state === null) {
            return $states[ $this->getState() ];
        } else {
            return $states[ $state ];
        }
    }

    public function enumStateNames()
    {
        return $this->stateNames;
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

    public function hasParentName($name)
    {
        foreach ($this->getParents() as $parent) {
            if ($parent->getName() === $name) {
                return true;
            }
        }

        return false;
    }

    public function removeParent($name)
    {
        $this->parents = array_filter(
            $this->parents,
            function (BpNode $parent) use ($name) {
                return $parent->getName() !== $name;
            }
        );

        return $this;
    }

    /**
     * @return BpNode[]
     */
    public function getParents()
    {
        return $this->parents;
    }

    /**
     * @return array
     */
    public function getPaths()
    {
        if ($this->bp->hasRootNode($this->getName())) {
            return array(array($this->getName()));
        }

        $paths = array();
        foreach ($this->parents as $parent) {
            foreach ($parent->getPaths() as $path) {
                $path[] = $this->getName();
                $paths[] = $path;
            }
        }

        return $paths;
    }

    protected function stateToSortState($state)
    {
        if (array_key_exists($state, $this->stateToSortStateMap)) {
            return $this->stateToSortStateMap[$state];
        }

        throw new ProgrammingError(
            'Got invalid state for node %s: %s',
            $this->getName(),
            var_export($state, 1) . var_export($this->stateToSortStateMap, 1)
        );
    }

    protected function sortStateTostate($sortState)
    {
        $sortState = $sortState >> self::SHIFT_FLAGS;
        if (array_key_exists($sortState, $this->sortStateToStateMap)) {
            return $this->sortStateToStateMap[$sortState];
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
