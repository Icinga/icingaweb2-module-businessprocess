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
            return false;
        }

        $name = $this->getNodeName();
        if ($this->parent !== null) {
            if (! $config->hasBpNode($this->parent)) {
                return false;
            }
            $parent = $config->getBpNode($this->parent);
            if (! $parent->hasChild($name)) {
                return false;
            }

            $nodes = $parent->getChildNames();
            if (! isset($nodes[$this->from]) || $nodes[$this->from] !== $name) {
                return false;
            }
        } else {
            if (! $config->hasNode($name)) {
                return false;
            }

            if ($config->getBpNode($name)->getDisplay() !== $this->getFrom()) {
                return false;
            }
        }

        if ($this->parent !== $this->newParent) {
            if ($this->newParent !== null) {
                if (! $config->hasBpNode($this->newParent)) {
                    return false;
                }

                $childrenCount = $config->getBpNode($this->newParent)->countChildren();
            } else {
                $childrenCount = $config->countChildren();
            }

            if ($this->getTo() > 0 && $childrenCount < $this->getTo()) {
                return false;
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
                foreach ($newNodes as $_ => $newNode) {
                    /** @var BpNode $newNode */
                    if ($newNode->getDisplay() > 0) {
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
