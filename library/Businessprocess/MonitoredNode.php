<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\Link;

abstract class MonitoredNode extends Node
{
    abstract public function getUrl();

    public function getLink()
    {
        if ($this->isMissing()) {
            return Link::create($this->getAlias(), '#');
        } else {
            return Link::create($this->getAlias(), $this->getUrl());
        }
    }

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
