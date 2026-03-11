<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Businessprocess\Web\Component;

use Icinga\Module\Businessprocess\Web\Component\Tabs;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class TabsTest extends BaseTestCase
{
    public function testEmptyTabsCanBeInstantiated()
    {
        $this->assertInstanceOf(
            'Icinga\Module\Businessprocess\Web\Component\Tabs',
            new Tabs()
        );
    }
}
