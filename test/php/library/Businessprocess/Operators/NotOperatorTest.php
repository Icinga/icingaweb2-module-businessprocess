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
            'a = !b',
            'a = ! b',
            'a = b ! c ! d',
            'a = ! b ! c ! d !',
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
        $expression = 'a = !b';
        $bp = $storage->loadFromString('dummy', $expression);
        $a = $bp->getNode('a');
        $b = $bp->createBp('b')->setState(3);
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
        $bp->setNodeState('b', 2);
        $bp->setNodeState('c', 2);
        $bp->setNodeState('d', 2);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testThreeTimesUnknownIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 3);
        $bp->setNodeState('c', 3);
        $bp->setNodeState('d', 3);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testThreeTimesWarningIsWarning()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 1);
        $bp->setNodeState('c', 1);
        $bp->setNodeState('d', 1);

        $this->assertEquals(
            'WARNING',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testThreeTimesOkIsCritical()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 0);
        $bp->setNodeState('c', 0);
        $bp->setNodeState('d', 0);

        $this->assertEquals(
            'CRITICAL',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testNotOkAndWarningAndCriticalIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 0);
        $bp->setNodeState('c', 1);
        $bp->setNodeState('d', 2);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testNotWarningAndUnknownAndCriticalIsOk()
    {
        $bp = $this->getBp();
        $bp->setNodeState('b', 3);
        $bp->setNodeState('c', 2);
        $bp->setNodeState('d', 1);

        $this->assertEquals(
            'OK',
            $bp->getNode('a')->getStateName()
        );
    }

    public function testNotTwoTimesWarningAndOkIsWarning()
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

    /**
     * @return BpConfig
     */
    protected function getBp()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = ! b ! c ! d';
        $bp = $storage->loadFromString('dummy', $expression);
        $bp->createBp('b');
        $bp->createBp('c');
        $bp->createBp('d');

        return $bp;
    }
}
