<?php

namespace Icinga\Module\Businessprocess;

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

    public function hasChildren()
    {
        return false;
    }

    public function countChildren()
    {
        return 0;
    }

    public function getChildren()
    {
        return array();
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
     
    public function renderHtml($view, $prefix = '')
    {
        $id = $this->getId($prefix);
        $state = strtolower($this->getStateName());
        $handled = $this->isAcknowledged() || $this->isInDowntime();
        $html = sprintf(
            '<table class="bp %s%s%s" id="%s"><tbody><tr>',
            $state === 'ok' ? 'ok' : 'problem ' . $state,
            $handled ? ' handled' : '',
            $this->hasChildren() ? ' operator process' : ' node service',
            $id
        );

        if ($this->hasChildren()) {
            $html .= sprintf(
                '<th%s><span class="op">%s</span></th>',
                sprintf(' rowspan="%d"', $this->countChildren() + 1),
                $this->operatorHtml()
            );
        }


        $title = preg_replace('~(</a>)~', implode('', $this->getIcons($view)) . '$1', $this->renderLink($view));
        if ($this->hasInfoUrl()) {
            $title = ' <a href="' . $this->getInfoUrl() . '" title="'
                  . mt('businessprocess', 'More information') . ': ' . $this->getInfoUrl()
                  . '" style="float: right">'
                  . $view->icon('help')
                  . '</a>' . $title;
        }

        $html .= sprintf(
            '<td>%s</td></tr>',
            $title
        );
        foreach ($this->getChildren() as $name => $child) {
            $html .= '<tr><td>' . $child->renderHtml($view, $id . '-') . '</td></tr>';
        }
        $html .= '</tbody></table>';
        return $html;
    }

    public function renderLink($view)
    {
        return '<a href="#">' . $this->name . '</a>';
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

    public function __toString()
    {
        return $this->name;
    }

    public function __destruct()
    {
        unset($this->parent);
    }
}
