<?php

namespace Icinga\Module\Businessprocess;

use ipl\Html\Html;

abstract class MonitoredNode extends Node
{
    abstract public function getUrl();

    public function getLink()
    {
        if ($this->isMissing()) {
            return Html::tag('a', ['href' => '#'], $this->getAlias());
        } else {
            return Html::tag('a', ['href' => $this->getUrl()], $this->getAlias());
        }
    }
}
