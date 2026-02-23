<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\ProvidedHook\Monitoring;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Monitoring\Hook\HostActionsHook;
use Icinga\Module\Monitoring\Object\Host;

class HostActions extends HostActionsHook
{
    public function getActionsForHost(Host $host)
    {
        $label = mt('businessprocess', 'Business Impact');
        return array(
            $label => 'businessprocess/node/impact?name='
                . rawurlencode(BpConfig::joinNodeName($host->getName(), 'Hoststatus'))
        );
    }
}
