<?php

namespace Tests\Icinga\Module\Businessprocess\Operator;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class OrOperatorTest extends BaseTestCase
{
    public function testTheOperatorCanBeParsed()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expressions = array(
            'a = b;c',
            'a = b;c | c;d | d;e',
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
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 2);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testTwoTimesCriticalOrUnknownIsUnknown()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 3);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'UNKNOWN',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testCriticalOrWarningOrOkIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 0);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testUnknownOrWarningOrCriticalIsWarning()
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

    public function testTwoTimesWarningAndOkIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 0);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 1);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testThreeTimesWarningIsWarning()
    {
        $bp = $this->getBp();
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
    protected function getBp()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = b;c | c;d | d;e';
        $bp = $storage->loadFromString('dummy', $expression);

        return $bp;
    }
}
