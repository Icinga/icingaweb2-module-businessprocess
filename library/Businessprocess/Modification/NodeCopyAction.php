<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Common\Sort;

class NodeCopyAction extends NodeAction
{
    use Sort;

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

        $display = 1;
        if ($config->getMetadata()->isManuallyOrdered()) {
            $rootNodes = self::applyManualSorting($config->getRootNodes());
            $display = end($rootNodes)->getDisplay() + 1;
        }

        $config->addRootNode($name)
            ->getBpNode($name)
            ->setDisplay($display);
    }
}
