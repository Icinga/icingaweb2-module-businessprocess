<?php

namespace Tests\Icinga\Module\Businessprocess\Operator;

use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\ServiceNode;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Web\View;

class ServiceNodeTest extends BaseTestCase
{
    public function testReturnsCorrectHostName()
    {
        $this->assertEquals(
            'localhost',
            $this->pingOnLocalhost()->getHostname()
        );
    }

    public function testReturnsCorrectServiceDescription()
    {
        $this->assertEquals(
            'ping <> pong',
            $this->pingOnLocalhost()->getServiceDescription()
        );
    }

    public function testReturnsCorrectAlias()
    {
        $this->assertEquals(
            'localhost: ping <> pong',
            $this->pingOnLocalhost()->getAlias()
        );
    }

    public function testRendersCorrectLink()
    {
        $this->assertEquals(
            '<a href="/icingaweb2/monitoring/service/show?host=localhost&amp;service=ping%20%3C%3E%20pong">'
            . 'localhost: ping &lt;&gt; pong</a>',
            $this->pingOnLocalhost()->renderLink(new View())
        );
    }

    /**
     * @return ServiceNode
     */
    protected function pingOnLocalhost()
    {
        $bp = new BusinessProcess();
        return new ServiceNode($bp, (object) array(
            'hostname' => 'localhost',
            'service'  => 'ping <> pong',
            'state'    => 0,
        ));
    }
}
