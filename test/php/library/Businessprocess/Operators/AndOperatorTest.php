<?php

namespace Tests\Icinga\Module\Businessprocess\Operator;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class AndOperatorTest extends BaseTestCase
{
    public function testTheOperatorCanBeParsed()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expressions = array(
            'a = b',
            'a = b & c & d',
        );

        foreach ($expressions as $expression) {
            $this->assertInstanceOf(
                'Icinga\\Module\\Businessprocess\\BpConfig',
                $storage->loadFromString('dummy', $expression)
            );
        }
    }

    public function testThreeTimesCriticalIsCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 2);
        $bp->setNodeState('c', 2);
        $bp->setNodeState('d', 2);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoTimesCriticalAndOkIsCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 2);
        $bp->setNodeState('c', 0);
        $bp->setNodeState('d', 2);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testCriticalAndWarningAndOkIsCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 2);
        $bp->setNodeState('c', 1);
        $bp->setNodeState('d', 0);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testUnknownAndWarningAndOkIsUnknown()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 0);
        $bp->setNodeState('c', 1);
        $bp->setNodeState('d', 3);

        $this->assertEquals(
            'UNKNOWN',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoTimesWarningAndOkIsWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 0);
        $bp->setNodeState('c', 1);
        $bp->setNodeState('d', 1);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testThreeTimesOkIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 0);
        $bp->setNodeState('c', 0);
        $bp->setNodeState('d', 0);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testSimpleAndOperationWorksCorrectly()
    {
        $bp = new BpConfig();
        $bp->throwErrors();
        $host = $bp->createHost('localhost')->setState(1);
        $service = $bp->createService('localhost', 'ping')->setState(1);
        $p = $bp->createBp('p');
        $p->addChild($host);
        $p->addChild($service);

        $this->assertEquals(
            'DOWN',
            $host->getStateName()
        );

        $this->assertEquals(
            'WARNING',
            $service->getStateName()
        );

        $this->assertEquals(
            'CRITICAL',
            $p->getStateName()
        );
    }

    public function testSimpleOrOperationWorksCorrectly()
    {
        $bp = new BpConfig();
        $bp->throwErrors();
        $host = $bp->createHost('localhost')->setState(1);
        $service = $bp->createService('localhost', 'ping')->setState(1);
        $p = $bp->createBp('p', '|');
        $p->addChild($host);
        $p->addChild($service);

        $this->assertEquals('DOWN', $host->getStateName());
        $this->assertEquals('WARNING', $service->getStateName());
        $this->assertEquals('WARNING', $p->getStateName());
    }

    public function testPendingIsAccepted()
    {
        $bp = new BpConfig();
        $host = $bp->createHost('localhost')->setState(99);
        $service = $bp->createService('localhost', 'ping')->setState(99);
        $p = $bp->createBp('p')
            ->addChild($host)
            ->addChild($service);

        $this->assertEquals(
            'PENDING',
            $p->getStateName()
        );
    }

    public function testWhetherWarningIsWorseThanPending()
    {
        $bp = new BpConfig();
        $host = $bp->createHost('localhost')->setState(99);
        $service = $bp->createService('localhost', 'ping')->setState(1);
        $p = $bp->createBp('p')
            ->addChild($host)
            ->addChild($service);

        $this->assertEquals(
            'WARNING',
            $p->getStateName()
        );
    }

    public function testPendingIsWorseThanUpOrOk()
    {
        $bp = new BpConfig();
        $host = $bp->createHost('localhost')->setState(99);
        $service = $bp->createService('localhost', 'ping')->setState(0);
        $p = $bp->createBp('p')
            ->addChild($host)
            ->addChild($service);

        $this->assertEquals(
            'PENDING',
            $p->getStateName()
        );

        $p->clearState();
        $host->setState(0);
        $service->setState(99);

        $this->assertEquals(
            'PENDING',
            $p->getStateName()
        );
    }

    /**
     * @return BpConfig
     */
    protected function getBp()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = b & c & d';
        $bp = $storage->loadFromString('dummy', $expression);
        $bp->createBp('b');
        $bp->createBp('c');
        $bp->createBp('d');

        return $bp;
    }
}
