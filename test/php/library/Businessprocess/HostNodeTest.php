<?php

namespace Tests\Icinga\Module\Businessprocess\Operator;

use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\HostNode;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Web\View;

class HostNodeTest extends BaseTestCase
{
    public function testReturnsCorrectHostName()
    {
        $this->assertEquals(
            'localhost',
            $this->localhost()->getHostname()
        );
    }

    public function testReturnsCorrectIdentifierWhenCastedToString()
    {
        $this->assertEquals(
            'localhost;Hoststatus',
            (string) $this->localhost()
        );
    }

    public function testReturnsCorrectAlias()
    {
        $this->assertEquals(
            'localhost',
            $this->localhost()->getAlias()
        );
    }

    public function testRendersCorrectLink()
    {
        $this->assertEquals(
            '<a href="/icingaweb2/monitoring/host/show?host=localhost">'
            . 'localhost</a>',
            $this->localhost()->getLink()->render()
        );
    }

    /**
     * @return HostNode
     */
    protected function localhost()
    {
        $bp = new BusinessProcess();
        return new HostNode($bp, (object) array(
            'hostname' => 'localhost',
            'state'    => 0,
        ));
    }
}
