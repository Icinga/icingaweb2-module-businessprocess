<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;

class NodeMoveAction extends NodeAction
{
    /**
     * @var string
     */
    protected $parent;

    /**
     * @var string
     */
    protected $newParent;

    /**
     * @var int
     */
    protected $from;

    /**
     * @var int
     */
    protected $to;

    protected $preserveProperties = ['parent', 'newParent', 'from', 'to'];

    public function setParent($name)
    {
        $this->parent = $name;
    }

    public function getParent()
    {
        return $this->parent;
    }

    public function setNewParent($name)
    {
        $this->newParent = $name;
    }

    public function getNewParent()
    {
        return $this->newParent;
    }

    public function setFrom($from)
    {
        $this->from = (int) $from;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function setTo($to)
    {
        $this->to = (int) $to;
    }

    public function getTo()
    {
        return $this->to;
    }

    public function appliesTo(BpConfig $config)
    {
        if (! $config->getMetadata()->isManuallyOrdered()) {
            $this->error('Process configuration is not manually ordered yet');
        }

        $name = $this->getNodeName();
        if ($this->parent !== null) {
            if (! $config->hasBpNode($this->parent)) {
                $this->error('Parent process "%s" missing', $this->parent);
            }
            $parent = $config->getBpNode($this->parent);
            if (! $parent->hasChild($name)) {
                $this->error('Node "%s" not found in process "%s"', $name, $this->parent);
            }

            $nodes = $parent->getChildNames();
            if (! isset($nodes[$this->from]) || $nodes[$this->from] !== $name) {
                $this->error('Node "%s" not found at position %d', $name, $this->from);
            }
        } else {
            if (! $config->hasRootNode($name)) {
                $this->error('Toplevel process "%s" not found', $name);
            }

            $nodes = $config->listRootNodes();
            if (! isset($nodes[$this->from]) || $nodes[$this->from] !== $name) {
                $this->error('Toplevel process "%s" not found at position %d', $name, $this->from);
            }
        }

        if ($this->parent !== $this->newParent) {
            if ($this->newParent !== null) {
                if (! $config->hasBpNode($this->newParent)) {
                    $this->error('New parent process "%s" missing', $this->newParent);
                } elseif ($config->getBpNode($this->newParent)->hasChild($name)) {
                    $this->error(
                        'New parent process "%s" already has a node with the name "%s"',
                        $this->newParent,
                        $name
                    );
                }

                $childrenCount = $config->getBpNode($this->newParent)->countChildren();
                if ($this->to > 0 && $childrenCount < $this->to) {
                    $this->error(
                        'New parent process "%s" has not enough children. Target position %d out of range',
                        $this->newParent,
                        $this->to
                    );
                }
            } else {
                if ($config->hasRootNode($name)) {
                    $this->error('Process "%s" is already a toplevel process', $name);
                }

                $childrenCount = $config->countChildren();
                if ($this->to > 0 && $childrenCount < $this->to) {
                    $this->error(
                        'Process configuration has not enough toplevel processes. Target position %d out of range',
                        $this->to
                    );
                }
            }
        }

        return true;
    }

    public function applyTo(BpConfig $config)
    {
        $name = $this->getNodeName();
        if ($this->parent !== null) {
            $nodes = $config->getBpNode($this->parent)->getChildren();
        } else {
            $nodes = $config->getRootNodes();
        }

        $node = $nodes[$name];
        $nodes = array_merge(
            array_slice($nodes, 0, $this->from, true),
            array_slice($nodes, $this->from + 1, null, true)
        );
        if ($this->parent === $this->newParent) {
            $nodes = array_merge(
                array_slice($nodes, 0, $this->to, true),
                [$name => $node],
                array_slice($nodes, $this->to, null, true)
            );
        } else {
            if ($this->newParent !== null) {
                $newNodes = $config->getBpNode($this->newParent)->getChildren();
            } else {
                $newNodes = $config->getRootNodes();
            }

            $newNodes = array_merge(
                array_slice($newNodes, 0, $this->to, true),
                [$name => $node],
                array_slice($newNodes, $this->to, null, true)
            );

            if ($this->newParent !== null) {
                $config->getBpNode($this->newParent)->setChildNames(array_keys($newNodes));
            } else {
                $config->addRootNode($name);

                $i = 0;
                foreach ($newNodes as $newName => $newNode) {
                    /** @var BpNode $newNode */
                    if ($newNode->getDisplay() > 0 || $newName === $name) {
                        $i += 1;
                        if ($newNode->getDisplay() !== $i) {
                            $newNode->setDisplay($i);
                        }
                    }
                }
            }
        }

        if ($this->parent !== null) {
            $config->getBpNode($this->parent)->setChildNames(array_keys($nodes));
        } else {
            if ($this->newParent !== null) {
                $config->removeRootNode($name);
                $node->setDisplay(0);
            }

            $i = 0;
            foreach ($nodes as $_ => $oldNode) {
                /** @var BpNode $oldNode */
                if ($oldNode->getDisplay() > 0) {
                    $i += 1;
                    if ($oldNode->getDisplay() !== $i) {
                        $oldNode->setDisplay($i);
                    }
                }
            }
        }
    }
}
