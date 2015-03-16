<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Web\Session\SessionNamespace as Session;
use Icinga\Module\Businessprocess\NodeAction;

class ProcessChanges
{
    protected $changes = array();

    protected $session;

    protected $hasBeenModified = false;

    protected $sessionKey;

    private function __construct()
    {
    }

    public static function construct(BusinessProcess $bp, Session $session)
    {
        $key = 'changes.' . $bp->getName();
        $changes = new ProcessChanges();
        $changes->sessionKey = $key;

        if ($actions = $session->get($key)) {
            foreach ($actions as $string) {
                $changes->push(NodeAction::unserialize($string));
            }
        }
        $changes->session = $session;

        return $changes;
    }

    public function modifyNode(Node $node, $properties)
    {
        $action = new NodeModifyAction($node);
        $action->setNodeProperties($node, $properties);
        return $this->push($action);
    }

    public function createNode($nodeName, $properties, Node $parent = null)
    {
        $action = new NodeCreateAction($nodeName);
        $action->setProperties($properties);
        if ($parent !== null) {
            $action->setParent($parent);
        }
        return $this->push($action);
    }

    public function deleteNode(Node $node)
    {
        return $this->push(new NodeDeleteAction($node));
    }

    public function push(NodeAction $change)
    {
        $this->changes[] = $change;
        $this->hasBeenModified = true;
        return $this;
    }

    public function getChanges()
    {
        return $this->changes;
    }

    public function clear()
    {
        $this->hasBeenModified = true;
        $this->changes = array();
        $this->session->set($this->getSessionKey(), null);
        return $this;
    }

    public function isEmpty()
    {
        return $this->count() === 0;
    }

    public function count()
    {
        return count($this->changes);
    }

    public function shift()
    {
        if ($this->isEmpty()) {
            return false;
        }

        $this->hasBeenModified = true;
        return array_shift($this->changes);
    }

    public function pop()
    {
        if ($this->isEmpty()) {
            return false;
        }

        $this->hasBeenModified = true;
        return array_pop($this->changes);
    }

    protected function getSessionKey()
    {
        return $this->sessionKey;
    }

    protected function hasBeenModified()
    {
        return $this->hasBeenModified;
    }

    public function serialize()
    {
        $serialized = array();
        foreach ($this->getChanges() as $change) {
            $serialized[] = $change->serialize();
        }

        return $serialized;
    }

    public function __destruct()
    {
        if (! $this->hasBeenModified()) {
            unset($this->session);
            return;
        }
        $session = $this->session;
        $key = $this->getSessionKey();
        if (! $this->isEmpty()) {
            $session->set($key, $this->serialize());
        }
        unset($this->session);
    }
}
