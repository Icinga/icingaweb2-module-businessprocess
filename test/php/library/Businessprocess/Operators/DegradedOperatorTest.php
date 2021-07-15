<?php

namespace Tests\Icinga\Module\Businessprocess\Operator;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class DegradedOperatorTest extends BaseTestCase
{
    public function testDegradedOperatorCanBeParsed()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expressions = [
            'a = b;c',
            'a = b;c % c;d % d;e',
        ];

        foreach ($expressions as $expression) {
            $this->assertInstanceOf(
                'Icinga\\Module\\Businessprocess\\BpConfig',
                $storage->loadFromString('dummy', $expression)
            );
        }
    }

    public function testThreeTimesCriticalIsWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 2);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoTimesCriticalAndOkIsWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 0);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testCriticalAndWarningAndOkIsWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 0);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testUnknownAndWarningAndOkIsUnknown()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 0);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 3);

        $this->assertEquals(
            'UNKNOWN',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoTimesWarningAndOkIsWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 0);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 1);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testUnknownAndWarningAndCriticalIsWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 3);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testThreeTimesOkIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 0);
        $bp->setNodeState('c;d', 0);
        $bp->setNodeState('d;e', 0);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testSimpleDegOperationWorksCorrectly()
    {
        $bp = new BpConfig();
        $bp->throwErrors();
        $host = $bp->createHost('localhost')->setState(0);
        $service = $bp->createService('localhost', 'ping')->setState(2);
        $p = $bp->createBp('p');
        $p->setOperator('%');
        $p->addChild($host);
        $p->addChild($service);

        $this->assertEquals(
            'UP',
            $host->getStateName()
        );

        $this->assertEquals(
            'CRITICAL',
            $service->getStateName()
        );

        $this->assertEquals(
            'WARNING',
            $p->getStateName()
        );
    }

    /**
     * @return BpConfig
     */
    protected function getBp()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = b;c % c;d % d;e';
        $bp = $storage->loadFromString('dummy', $expression);
        $bp->createBp('b');
        $bp->createBp('c');
        $bp->createBp('d');

        return $bp;
    }
}
