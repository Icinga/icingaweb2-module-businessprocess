<?php

namespace Tests\Icinga\Module\Businessprocess\Storage;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class NotOperatorTest extends BaseTestCase
{
    public function testWhetherNegationsCanBeParsed()
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
                'Icinga\\Module\\Businessprocess\\Businessprocess',
                $storage->loadFromString('dummy', $expression)
            );
        }
    }

    public function testWhetherNegationsMatch()
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

    public function testWhetherNegatingMultipleValuesBehavesLikeNotAnd()
    {
        $storage = new LegacyStorage($this->emptyConfigSection());
        $expression = 'a = ! b ! c ! d';
        $bp = $storage->loadFromString('dummy', $expression);
        $a = $bp->getNode('a');
        $b = $bp->createBp('b')->setState(3);
        $c = $bp->createBp('c')->setState(3);
        $d = $bp->createBp('d')->setState(3);
        $this->assertEquals(
            'OK',
            $a->getStateName()
        );

        $a->clearState();
        $b->setState(0);
        $this->assertEquals(
            'OK',
            $a->getStateName()
        );

        $a->clearState();
        $c->setState(0);
        $this->assertEquals(
            'OK',
            $a->getStateName()
        );

        $a->clearState();
        $d->setState(0);
        $this->assertEquals(
            'CRITICAL',
            $a->getStateName()
        );
    }
}

