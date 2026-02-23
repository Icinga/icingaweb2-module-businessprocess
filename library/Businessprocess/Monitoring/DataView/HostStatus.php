<?php

// SPDX-FileCopyrightText: 2023 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Monitoring\DataView;

use Icinga\Data\ConnectionInterface;
use Icinga\Module\Businessprocess\Monitoring\Backend\Ido\Query\HostStatusQuery;

class HostStatus extends \Icinga\Module\Monitoring\DataView\Hoststatus
{
    public function __construct(ConnectionInterface $connection, ?array $columns = null)
    {
        parent::__construct($connection, $columns);

        $this->query = new HostStatusQuery($connection->getResource(), $columns);
    }
}
