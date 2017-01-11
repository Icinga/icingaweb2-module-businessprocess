<?php

namespace Tests\Icinga\Module\Businessprocess\Html;

use Icinga\Module\Businessprocess\Html\Text;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class TextTest extends BaseTestCase
{
    public function testTextIsReturnedAsGiven()
    {
        $this->assertEquals(
            'A & O',
            Text::create('A & O')->getText()
        );
    }

    public function testTextIsEscapedWhenRendered()
    {
        $this->assertEquals(
            'A &amp; O',
            Text::create('A & O')->render()
        );
    }
}
