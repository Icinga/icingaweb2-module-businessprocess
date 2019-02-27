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
            'a = 1 of: b;c',
            'a = 2 of: b;c + c;d + d;e',
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
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 2);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfTwoTimesCriticalAndUnknownAreAtLeastCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 3);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfCriticalAndWarningAndOkAreAtLeastCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 0);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfUnknownAndWarningAndCriticalAreAtLeastCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 3);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfTwoTimesWarningAndUnknownAreAtLeastUnknown()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 3);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 1);

        $this->assertEquals(
            'UNKNOWN',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoOfThreeTimesOkAreAtLeastOk()
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

    public function testTenWithAllOk()
    {
        $bp = $this->getBp(10, 9, 0);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTenWithOnlyTwoCritical()
    {
        $bp = $this->getBp(10, 8, 0);
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 2);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTenWithThreeCritical()
    {
        $bp = $this->getBp(10, 8, 0);
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 2);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTenWithThreeWarning()
    {
        $bp = $this->getBp(10, 8, 0);
        $bp->setNodeState('b;c', 1);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 1);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    /**
     * @return BpConfig
     */
    protected function getBp($count = 3, $min = 2, $defaultState = null)
    {
        $names = array();
        $a = 97;
        for ($i = 1; $i <= $count; $i++) {
            $names[] = chr($a + $i) . ';' . chr($a + $i + 1);
        }

        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = sprintf('a = %d of: %s', $min, join(' + ', $names));
        $bp = $storage->loadFromString('dummy', $expression);
        foreach ($names as $n) {
            if ($defaultState !== null) {
                $bp->setNodeState($n, $defaultState);
            }
        }

        return $bp;
    }
}
