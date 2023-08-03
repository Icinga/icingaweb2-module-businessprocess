<?php

namespace Tests\Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class BpConfigTest extends BaseTestCase
{
    public function testJoinNodeName()
    {
        $this->assertSame(
            'foo;bar',
            BpConfig::joinNodeName('foo', 'bar')
        );
        $this->assertSame(
            'foo\;bar',
            BpConfig::joinNodeName('foo;bar')
        );
        $this->assertSame(
            'foo\;bar;baroof',
            BpConfig::joinNodeName('foo;bar', 'baroof')
        );
        $this->assertSame(
            'foo\;bar;bar;oof',
            BpConfig::joinNodeName('foo;bar', 'bar;oof')
        );
    }

    public function testSplitNodeName()
    {
        $this->assertSame(
            ['foo', 'bar'],
            BpConfig::splitNodeName('foo;bar')
        );
        $this->assertSame(
            ['foo;bar', null],
            BpConfig::splitNodeName('foo\;bar')
        );
        $this->assertSame(
            ['foo;bar', 'baroof'],
            BpConfig::splitNodeName('foo\;bar;baroof')
        );
        $this->assertSame(
            ['foo;bar', 'bar;oof'],
            BpConfig::splitNodeName('foo\;bar;bar;oof')
        );
    }
}
