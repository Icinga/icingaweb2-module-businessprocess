<?php

namespace Tests\Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\BpConfig;
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
            $this->localhost()->getName()
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
            '<a href="/icingaweb2/businessprocess/host/show?host=localhost">'
            . 'localhost</a>',
            $this->localhost()->getLink()->render()
        );
    }

    public function testSettingAnInvalidStateFails()
    {
        $this->expectException(\Icinga\Exception\ProgrammingError::class);
        $bp = new BpConfig();
        $host = $bp->createHost('localhost')->setState(98);
        $bp->createBp('p')->addChild($host)->getState();
    }

    /**
     * @return HostNode
     */
    protected function localhost()
    {
        $bp = new BpConfig();
        return (new HostNode((object) array(
            'hostname' => 'localhost',
            'state'    => 0,
        )))->setBpConfig($bp)->setAlias('localhost');
    }
}
