<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess;

use ipl\Html\Html;

abstract class MonitoredNode extends Node
{
    abstract public function getUrl();

    public function getLink()
    {
        if ($this->isMissing()) {
            return Html::tag('a', ['href' => '#'], $this->getAlias() ?? $this->getName());
        } else {
            return Html::tag('a', ['href' => $this->getUrl()], $this->getAlias() ?? $this->getName());
        }
    }
}
