<?php

namespace Icinga\Module\Businessprocess;

class NodeRemoveAction extends NodeAction
{
    public function appliesTo(BusinessProcess $bp)
    {
        return $bp->hasNode($this->getNodeName());
    }

    public function applyTo(BusinessProcess $bp)
    {
        $bp->removeNode($this->getNodeName());
    }
}
