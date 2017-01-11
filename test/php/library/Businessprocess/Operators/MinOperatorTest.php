<?php

namespace Tests\Icinga\Module\Businessprocess\Operator;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class MinOperatorTest extends BaseTestCase
{
    public function testTheOperatorCanBeParsed()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expressions = array(
            'a = 1 of: b',
            'a = 2 of: b + c + d',
        );
        $this->getName();
        foreach ($expressions as $expression) {
            $this->assertInstanceOf(
                'Icinga\\Module\\Businessprocess\\BpConfig',
                $storage->loadFromString('dummy', $expression)
            );
        }
    }
    public function testTwoOfThreeTimesCriticalAreAtLeastCritical()
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

    public function testTwoOfTwoTimesCriticalAndUnknownAreAtLeastCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 2);
        $bp->setNodeState('c', 3);
        $bp->setNodeState('d', 2);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfCriticalAndWarningAndOkAreAtLeastWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 2);
        $bp->setNodeState('c', 1);
        $bp->setNodeState('d', 0);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfUnknownAndWarningAndCriticalAreAtLeastUnknown()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 2);
        $bp->setNodeState('c', 1);
        $bp->setNodeState('d', 3);

        $this->assertEquals(
            'UNKNOWN',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfTwoTimesWarningAndUnknownAreAtLeastWarning()
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

    public function testTwoOfThreeTimesOkAreAtLeastOk()
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

    /**
     * @return BpConfig
     */
    protected function getBp()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = 2 of: b + c + d';
        $bp = $storage->loadFromString('dummy', $expression);
        $bp->createBp('b');
        $bp->createBp('c');
        $bp->createBp('d');

        return $bp;
    }
}
