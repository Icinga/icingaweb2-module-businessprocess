<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Node;
use Icinga\Web\Session\SessionNamespace as Session;

class ProcessChanges
{
    /** @var NodeAction[] */
    protected $changes = array();

    /** @var Session */
    protected $session;

    /** @var BpConfig */
    protected $config;

    /** @var bool */
    protected $hasBeenModified = false;

    /** @var string Session storage key for this processes changes */
    protected $sessionKey;

    /**
     * ProcessChanges constructor.
     *
     * Direct access is not allowed
     */
    private function __construct()
    {
    }

    /**
     * @param BpConfig $bp
     * @param Session $session
     *
     * @return ProcessChanges
     */
    public static function construct(BpConfig $bp, Session $session)
    {
        $key = 'changes.' . $bp->getName();
        $changes = new ProcessChanges();
        $changes->sessionKey = $key;

        if ($actions = $session->get($key)) {
            foreach ($actions as $string) {
                $changes->push(NodeAction::unSerialize($string));
            }
        }
        $changes->session = $session;
        $changes->config = $bp;

        return $changes;
    }

    /**
     * @param Node $node
     * @param $properties
     *
     * @return $this
     */
    public function modifyNode(Node $node, $properties)
    {
        $action = new NodeModifyAction($node);
        $action->setNodeProperties($node, $properties);
        return $this->push($action, true);
    }

    /**
     * @param Node $node
     * @param $properties
     *
     * @return $this
     */
    public function addChildrenToNode($children, Node $node = null)
    {
        $action = new NodeAddChildrenAction($node);
        $action->setChildren($children);
        return $this->push($action, true);
    }

    /**
     * @param Node|string $nodeName
     * @param array $properties
     * @param Node $parent
     *
     * @return $this
     */
    public function createNode($nodeName, $properties, Node $parent = null)
    {
        $action = new NodeCreateAction($nodeName);
        $action->setProperties($properties);
        if ($parent !== null) {
            $action->setParent($parent);
        }
        return $this->push($action, true);
    }

    /**
     * @param $nodeName
     * @return $this
     */
    public function copyNode($nodeName)
    {
        $action = new NodeCopyAction($nodeName);
        return $this->push($action, true);
    }

    /**
     * @param Node $node
     * @param string $parentName
     * @return $this
     */
    public function deleteNode(Node $node, $parentName = null)
    {
        $action = new NodeRemoveAction($node);
        if ($parentName !== null) {
            $action->setParentName($parentName);
        }

        return $this->push($action, true);
    }

    /**
     * Move the given node
     *
     * @param   Node    $node
     * @param   int     $from
     * @param   int     $to
     * @param   string  $newParent
     * @param   string  $parent
     *
     * @return  $this
     */
    public function moveNode(Node $node, $from, $to, $newParent, $parent = null)
    {
        $action = new NodeMoveAction($node);
        $action->setParent($parent);
        $action->setNewParent($newParent);
        $action->setFrom($from);
        $action->setTo($to);

        return $this->push($action, true);
    }

    /**
     * Apply manual order on the entire bp configuration file
     *
     * @return  $this
     */
    public function applyManualOrder()
    {
        return $this->push(new NodeApplyManualOrderAction(), true);
    }

    /**
     * Add a new action to the stack
     *
     * @param   NodeAction  $change
     * @param   bool        $apply
     *
     * @return $this
     */
    public function push(NodeAction $change, $apply = false)
    {
        if ($apply && $change->appliesTo($this->config)) {
            $change->applyTo($this->config);
        }

        $this->changes[] = $change;
        $this->hasBeenModified = true;
        return $this;
    }

    /**
     * Get all stacked actions
     *
     * @return NodeAction[]
     */
    public function getChanges()
    {
        return $this->changes;
    }

    /**
     * Forget all changes and remove them from the Session
     *
     * @return $this
     */
    public function clear()
    {
        $this->hasBeenModified = true;
        $this->changes = array();
        $this->session->set($this->getSessionKey(), null);
        return $this;
    }

    /**
     * Whether there are no stacked changes
     *
     * @return bool
     */
    public function isEmpty()
    {
        return $this->count() === 0;
    }

    /**
     * Number of stacked changes
     *
     * @return int
     */
    public function count()
    {
        return count($this->changes);
    }

    /**
     * Get the first change on the stack, false if empty
     *
     * @return NodeAction|boolean
     */
    public function shift()
    {
        if ($this->isEmpty()) {
            return false;
        }

        $this->hasBeenModified = true;
        return array_shift($this->changes);
    }

    /**
     * Get the last change on the stack, false if empty
     *
     * @return NodeAction|boolean
     */
    public function pop()
    {
        if ($this->isEmpty()) {
            return false;
        }

        $this->hasBeenModified = true;
        return array_pop($this->changes);
    }

    /**
     * The identifier used for this processes changes in our Session storage
     *
     * @return string
     */
    protected function getSessionKey()
    {
        return $this->sessionKey;
    }

    protected function hasBeenModified()
    {
        return $this->hasBeenModified;
    }

    /**
     * @return array
     */
    public function serialize()
    {
        $serialized = array();
        foreach ($this->getChanges() as $change) {
            $serialized[] = $change->serialize();
        }

        return $serialized;
    }

    /**
     * Persist to session on destruction
     */
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
