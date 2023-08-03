<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Common\Sort;

class NodeApplyManualOrderAction extends NodeAction
{
    use Sort;

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
                $node->setChildNames(array_keys(
                    $this->setSort('display_name asc')
                        ->sort($node->getChildren())
                ));
            }
        }

        $config->getMetadata()->set('ManualOrder', 'yes');
    }
}
