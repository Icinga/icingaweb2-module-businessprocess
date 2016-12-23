<?php

namespace Tests\Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\HostNode;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

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
     * @expectedException \Icinga\Exception\ProgrammingError
     */
    public function testWhetherSettingAnInvalidStateFails()
    {
        $bp = new BusinessProcess();
        $host = $bp->createHost('localhost')->setState(98);
        $bp->createBp('p')->addChild($host)->getState();
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
