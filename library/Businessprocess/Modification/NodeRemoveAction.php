<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;

/**
 * NodeRemoveAction
 *
 * Tracks removed nodes
 *
 * @package Icinga\Module\Businessprocess
 */
class NodeRemoveAction extends NodeAction
{
    protected $preserveProperties = array('parentName');

    protected $parentName;

    /**
     * @param $parentName
     * @return $this
     */
    public function setParentName($parentName = null)
    {
        $this->parentName = $parentName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * @inheritdoc
     */
    public function appliesTo(BpConfig $config)
    {
        $parent = $this->getParentName();
        if ($parent === null) {
            return $config->hasNode($this->getNodeName());
        } else {
            return $config->hasNode($this->getNodeName()) && $config->hasNode($this->getParentName());
        }
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BpConfig $config)
    {
        $config->calculateAllStates();
        $name = $this->getNodeName();
        $parentName = $this->getParentName();
        if ($parentName === null) {
            $config->removeNode($name);
        } else {
            $node = $config->getNode($name);
            $parent = $config->getBpNode($parentName);
            $parent->getState();
            $parent->removeChild($name);
            $node->removeParent($parentName);
            if (! $node->hasParents()) {
                $config->removeNode($name);
            }
        }
    }
}
