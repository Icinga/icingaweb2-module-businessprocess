<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;

class NodeApplyManualOrderAction extends NodeAction
{
    public function appliesTo(BpConfig $config)
    {
        return $config->getMetadata()->get('ManualOrder') !== 'yes';
    }

    public function applyTo(BpConfig $config)
    {
        $i = 0;
        foreach ($config->getRootNodes() as $name => $node) {
            /** @var BpNode $node */
            if ($node->getDisplay() > 0) {
                $node->setDisplay(++$i);
            }

            if ($node->hasChildren()) {
                $node->setChildNames($node->getChildNames());
            }
        }

        $config->getMetadata()->set('ManualOrder', 'yes');
    }
}
