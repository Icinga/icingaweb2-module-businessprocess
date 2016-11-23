<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Web\Component\Container;
use Icinga\Module\Businessprocess\Web\Component\Link;

abstract class MonitoredNode extends Node
{
    protected function prepareActions(Container $actions)
    {
        $actions->add(
            $url = Link::create(
                $actions->view()->translate('Simulate a specific state'),
                'businessprocess/process/show?addSimulation&unlocked',
                array(
                    'config' => $this->bp->getName(),
                    'simulationNode' => $this->name
                ),
                array('class' => 'icon-magic')
            )
        );
    }
}
