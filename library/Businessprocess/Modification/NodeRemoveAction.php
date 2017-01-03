<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BusinessProcess;

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
    public function appliesTo(BusinessProcess $bp)
    {
        $parent = $this->getParentName();
        if ($parent === null) {
            return $bp->hasNode($this->getNodeName());
        } else {
            return $bp->hasNode($this->getNodeName()) && $bp->hasNode($this->getParentName()) ;
        }
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BusinessProcess $bp)
    {
        $parent = $this->getParentName();
        if ($parent === null) {
            $bp->removeNode($this->getNodeName());
        } else {
            $node = $bp->getNode($this->getNodeName());
            $node->removeParent($parent);
            if (! $node->hasParents()) {
                $bp->removeNode($this->getNodeName());
            }
        }
    }
}
