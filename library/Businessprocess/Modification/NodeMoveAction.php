<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;

class NodeMoveAction extends NodeAction
{
    /**
     * @var string
     */
    protected $parentName;

    /**
     * @var int
     */
    protected $from;

    /**
     * @var int
     */
    protected $to;

    protected $preserveProperties = ['parentName', 'from', 'to'];

    public function setParentName($name)
    {
        $this->parentName = $name;
    }

    public function getParentName()
    {
        return $this->parentName;
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
        if ($this->parentName !== null) {
            if (! $config->hasBpNode($this->parentName)) {
                return false;
            }
            $parent = $config->getBpNode($this->parentName);
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

        return true;
    }

    public function applyTo(BpConfig $config)
    {
        $name = $this->getNodeName();
        if ($this->parentName !== null) {
            $nodes = $config->getBpNode($this->parentName)->getChildren();
        } else {
            $nodes = $config->getRootNodes();
        }

        $node = $nodes[$name];
        $nodes = array_merge(
            array_slice($nodes, 0, $this->from, true),
            array_slice($nodes, $this->from + 1, null, true)
        );
        $nodes = array_merge(
            array_slice($nodes, 0, $this->to, true),
            [$name => $node],
            array_slice($nodes, $this->to, null, true)
        );

        if ($this->parentName !== null) {
            $config->getBpNode($this->parentName)->setChildNames(array_keys($nodes));
        } else {
            $i = 0;
            foreach ($nodes as $name => $node) {
                /** @var BpNode $node */
                if ($node->getDisplay() > 0) {
                    $i += 1;
                    if ($node->getDisplay() !== $i) {
                        $node->setDisplay($i);
                    }
                }
            }
        }
    }
}
