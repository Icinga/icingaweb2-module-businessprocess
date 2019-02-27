<?php

namespace Tests\Icinga\Module\Businessprocess\Operator;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class NotOperatorTest extends BaseTestCase
{
    public function testNegationOperatorsCanBeParsed()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expressions = array(
            'a = !b;c',
            'a = ! b;c',
            'a = b;c ! c;d ! d;e',
            'a = ! b;c ! c;d ! d;e !',
        );

        foreach ($expressions as $expression) {
            $this->assertInstanceOf(
                'Icinga\\Module\\Businessprocess\\BpConfig',
                $storage->loadFromString('dummy', $expression)
            );
        }
    }

    public function testASimpleNegationGivesTheCorrectResult()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = !b;c';
        $bp = $storage->loadFromString('dummy', $expression);
        $a = $bp->getNode('a');
        $b = $bp->getNode('b;c')->setState(3);
        $this->assertEquals(
            'OK',
            $a->getStateName()
        );

        $a->clearState();
        $b->setState(0);
        $this->assertEquals(
            'CRITICAL',
            $a->getStateName()
        );
    }

    public function testThreeTimesCriticalIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 2);
        $bp->setNodeState('c;d', 2);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testThreeTimesUnknownIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 3);
        $bp->setNodeState('c;d', 3);
        $bp->setNodeState('d;e', 3);

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

    public function testThreeTimesOkIsCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 0);
        $bp->setNodeState('c;d', 0);
        $bp->setNodeState('d;e', 0);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testNotOkAndWarningAndCriticalIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 0);
        $bp->setNodeState('c;d', 1);
        $bp->setNodeState('d;e', 2);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testNotWarningAndUnknownAndCriticalIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b;c', 3);
        $bp->setNodeState('c;d', 2);
        $bp->setNodeState('d;e', 1);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testNotTwoTimesWarningAndOkIsWarning()
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

    /**
     * @return BpConfig
     */
    protected function getBp()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = ! b;c ! c;d ! d;e';
        $bp = $storage->loadFromString('dummy', $expression);

        return $bp;
    }
}
