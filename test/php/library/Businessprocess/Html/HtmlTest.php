<?php

namespace Tests\Icinga\Module\Businessprocess\Html;

use Icinga\Module\Businessprocess\Html\Html;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class HtmlTest extends BaseTestCase
{
    public function testContentIsRendered()
    {
        $h = new Html();
        $h->setContent('Some content');
        $this->assertEquals(
            'Some content',
            $h->render()
        );
    }

    public function testContentCanBeExtended()
    {
        $h = new Html();
        $h->setContent('Some content');
        $h->addContent('More content');
        $this->assertEquals(
            'Some contentMore content',
            $h->render()
        );
    }

    public function testSeparatorsAreRespected()
    {
        $h = new Html();
        $h->setContent('Some content');
        $h->setSeparator(', and ');
        $h->addContent('More content');
        $this->assertEquals(
            'Some content, and More content',
            $h->render()
        );
    }
}
