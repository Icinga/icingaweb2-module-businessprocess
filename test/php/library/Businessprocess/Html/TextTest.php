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

    public function testTextIsRenderedAsGivenWhenDeclaredBeingEscaped()
    {
        $this->assertEquals(
            'A & O',
            Text::create('A & O')->setEscaped()->render()
        );

        $this->assertEquals(
            'A & O',
            Text::create('A & O')->setEscaped(true)->render()
        );

        $this->assertEquals(
            'A &amp; O',
            Text::create('A & O')->setEscaped(false)->render()
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
