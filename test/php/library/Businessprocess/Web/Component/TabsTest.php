<?php

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
