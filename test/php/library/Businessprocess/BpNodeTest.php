<?php

namespace Tests\Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class BpNodeTest extends BaseTestCase
{
    /**
     * @expectedException \Icinga\Module\Businessprocess\Exception\NestingError
     */
    public function testThrowsNestingErrorWhenCheckedForLoops()
    {
        /** @var BpNode $bpNode */
        $bpNode = $this->makeLoop()->getNode('d');
        $bpNode->checkForLoops();
    }

    /**
     * @expectedExceptionMessage d -> a -> b -> c -> a
     * @expectedException \Icinga\Module\Businessprocess\Exception\NestingError
     */
    public function testNestingErrorReportsFullLoop()
    {
        /** @var BpNode $bpNode */
        $bpNode = $this->makeLoop()->getNode('d');
        $bpNode->checkForLoops();
    }

    public function testStateForALoopGivesUnknown()
    {
        $loop = $this->makeLoop();
        /** @var BpNode $bpNode */
        $bpNode = $loop->getNode('d');
        $this->assertEquals(
            'UNKNOWN',
            $bpNode->getStateName()
        );
    }
}
