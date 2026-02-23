<?php

// SPDX-FileCopyrightText: 2023 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Monitoring\Backend\Ido\Query;

class ServiceStatusQuery extends \Icinga\Module\Monitoring\Backend\Ido\Query\ServicestatusQuery
{
    use CustomVarJoinTemplateOverride;
}
