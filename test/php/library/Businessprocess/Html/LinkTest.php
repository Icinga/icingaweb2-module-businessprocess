<?php

namespace Tests\Icinga\Module\Businessprocess\Html;

use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class LinkTest extends BaseTestCase
{
    public function testContentFromFactoryIsRendered()
    {
        $l = Link::create('Click here', 'go/some/where');
        $this->assertEquals(
            'Click here',
            $l->renderContent()
        );
    }

    public function testSimpleLinkRendersCorrectly()
    {
        $l = Link::create('Click here', 'go/some/where');
        $this->assertEquals(
            '<a href="/icingaweb2/go/some/where">Click here</a>',
            $l->render()
        );
    }

    public function testLinkWithParamsRendersCorrectly()
    {
        $l = Link::create(
            'Click here',
            'go/some/where',
            array(
                'with' => 'me',
                'and'  => 'aDog'
            )
        );
        $this->assertEquals(
            '<a href="/icingaweb2/go/some/where?with=me&amp;and=aDog">Click here</a>',
            $l->render()
        );
    }
}
