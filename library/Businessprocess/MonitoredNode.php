<?php

namespace Icinga\Module\Businessprocess;

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
}
