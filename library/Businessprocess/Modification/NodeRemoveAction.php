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
    protected $preserveProperties = array('path');

    protected $path;

    /**
     * @param array $path
     * @return $this
     */
    public function setPath(array $path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @inheritdoc
     */
    public function appliesTo(BusinessProcess $bp)
    {
        $path = $this->getPath();
        if ($path === null) {
            return $bp->hasNodeByPath($this->getNodeName(), $this->getPath());
        } else {
            return $bp->hasNode($this->getNodeName());
        }
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BusinessProcess $bp)
    {
        $path = $this->getPath();
        if ($path === null) {
            $bp->removeNode($this->getNodeName());
        } else {
            $bp->removeNodeByPath($this->getNodeName(), $this->getPath());
        }
    }
}
