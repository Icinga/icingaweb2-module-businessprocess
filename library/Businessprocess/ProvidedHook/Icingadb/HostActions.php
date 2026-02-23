<?php

// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\ProvidedHook\Icingadb;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Icingadb\Hook\HostActionsHook;
use Icinga\Module\Icingadb\Model\Host;
use ipl\Web\Widget\Link;

class HostActions extends HostActionsHook
{
    public function getActionsForObject(Host $host): array
    {
        $label = mt('businessprocess', 'Business Impact');
        return array(
            new Link(
                $label,
                'businessprocess/node/impact?name='
                . rawurlencode(BpConfig::joinNodeName($host->name, 'Hoststatus'))
            )
        );
    }
}
