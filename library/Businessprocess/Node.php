<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Web\Url;
use Icinga\Exception\ProgrammingError;
use Icinga\Data\Filter\Filter;
use Exception;

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

    public function hasInfoUrl()
    {
        return false;
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
        $node->addParent($this);
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
        return static::$state_names[ $this->getState() ];
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

    public function isSimulationMode()
    {
        return $this->bp->isSimulationMode();
    }

    public function isEditMode()
    {
        return $this->bp->isEditMode();
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

    protected function stateToSortState($state)
    {
        if (array_key_exists($state, static::$stateToSortStateMap)) {
            return static::$stateToSortStateMap[$state];
        }

        throw new ProgrammingError('Got invalid state %s', $sort_state);
    }

    protected function sortStateTostate($sortState)
    {
        $sortState = $sortState >> self::SHIFT_FLAGS;

        if (array_key_exists($sortState, static::$sortStateToStateMap)) {
            return static::$sortStateToStateMap[$sortState];
        }

        throw new ProgrammingError('Got invalid sorting state %s', $sort_state);
    }

    protected function renderHtmlForChildren($view)
    {
        $html = '';
        if ($this->hasChildren()) {
            foreach ($this->getChildren() as $name => $child) {
                $html .= '<tr><td>'
                       . $child->renderHtml($view)
                       . '</td></tr>';
            }
        }

        return $html;
    }

    protected function getId($prefix = '')
    {
        return md5($prefix . (string) $this);
    }

    protected function getObjectClassName()
    {
        return $this->className;
    }

    protected function getStateClassNames()
    {
        $state = strtolower($this->getStateName());

        if ($this->isMissing()) {
            return array('missing');
        } elseif ($state === 'ok') {
            return array('ok');
        } else {
            return array('problem', $state);
        }
    }

    public function renderHtml($view, $prefix = '')
    {
        $id = $this->getId($prefix);
        $handled = $this->isAcknowledged() || $this->isInDowntime();

        $html = sprintf(
            '<table class="bp %s%s%s%s" id="%s"><tbody><tr>',
            implode(' ', $this->getStateClassNames()),
            $handled ? ' handled' : '',
            ($this->hasChildren() ? ' operator ' : ' node '),
            $this->getObjectClassName(),
            $id
        );

        if ($this->hasChildren()) {
            $html .= sprintf(
                '<th%s><span class="op">%s</span></th>',
                sprintf(' rowspan="%d"', $this->countChildren() + 1),
                $this->operatorHtml()
            );
        }


        $title = preg_replace(
            '~(</a>)~',
            implode('', $this->getIcons($view)) . '$1',
            $this->renderLink($view)
        );

        $title = preg_replace('#</a>#', ' ' . $view->timeSince($this->getLastStateChange()) . '</a>', $title);
        $icons = array();

        foreach ($this->getActionIcons($view) as $icon) {
            $icons[] = $icon;
        }
        
        if ($this->hasInfoUrl()) {
            $url = $this->getInfoUrl();
            $icons[] = $this->actionIcon(
                $view,
                'help',
                $url,
                sprintf('%s: %s', mt('businessprocess', 'More information'), $url)
            );
        }
        $title = implode("\n", $icons) . $title;

        $html .= sprintf(
            '<td>%s</td></tr>',
            $title
        );
        foreach ($this->getChildren() as $name => $child) {
            $html .= '<tr><td>' . $child->renderHtml($view, $id . '-') . '</td></tr>';
        }
        $html .= "</tbody></table>\n";
        return $html;
    }

    protected function getActionIcons($view)
    {
        return array();
    }

    protected function actionIcon($view, $icon, $url, $title)
    {
        if ($url instanceof Url || ! preg_match('~^https?://~', $url)) {
            $target = '';
        } else {
            $target = ' target="_blank"';
        }

        return sprintf(
            ' <a href="%s" %stitle="%s" style="float: right" data-base-target="bp-overlay">%s</a>',
            $url,
            $target,
            $view->escape($title),
            $view->icon($icon)
        );
    }

    public function renderLink($view)
    {
        return '<a href="#">' . ($this->hasAlias() ? $this->getAlias() : $this->name) . '</a>';
    }

    public function getIcons($view)
    {
        $icons = array();
        if ($this->isInDowntime()) {
            $icons[] = $view->icon('moon');
        }
        if ($this->isAcknowledged()) {
            $icons[] = $view->icon('ok');
        }
        return $icons;
    }

    public function operatorHtml()
    {
        return '&nbsp;';
    }

    public function toLegacyConfigString(& $rendered = array()) { return '';}
    //abstract public function toLegacyConfigString();

    public function __toString()
    {
        return $this->name;
    }

    public function __destruct()
    {
        $this->parents = array();
    }
}
