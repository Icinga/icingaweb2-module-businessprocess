<?php

namespace Tests\Icinga\Module\Businessprocess\Html;

use Icinga\Module\Businessprocess\Html\HtmlTag;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class HtmlTagTest extends BaseTestCase
{
    public function testHeaderIsRendered()
    {
        $h1 = HtmlTag::h1('Hea & der');
        $this->assertEquals(
            $h1->render(),
            '<h1>Hea &amp; der</h1>'
        );
    }
}
