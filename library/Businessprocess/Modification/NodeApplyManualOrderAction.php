<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;

class NodeApplyManualOrderAction extends NodeAction
{
    public function appliesTo(BpConfig $config)
    {
        return $config->getMetadata()->get('ManualOrder') !== 'yes';
    }

    public function applyTo(BpConfig $config)
    {
        $i = 0;
        foreach ($config->getBpNodes() as $name => $node) {
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
