<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;

class NodeCopyAction extends NodeAction
{
    /**
     * @param BpConfig $config
     * @return bool
     */
    public function appliesTo(BpConfig $config)
    {
        $name = $this->getNodeName();

        if (! $config->hasBpNode($name)) {
            $this->error('Process "%s" not found', $name);
        }

        if ($config->hasRootNode($name)) {
            $this->error('A toplevel node with name "%s" already exists', $name);
        }

        return true;
    }

    /**
     * @param BpConfig $config
     */
    public function applyTo(BpConfig $config)
    {
        $name = $this->getNodeName();
        $rootNodes = $config->getRootNodes();
        $config->addRootNode($name)
            ->getBpNode($name)
            ->setDisplay(end($rootNodes)->getDisplay() + 1);
    }
}
