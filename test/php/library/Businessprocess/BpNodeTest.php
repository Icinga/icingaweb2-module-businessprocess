<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Tests\Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class BpNodeTest extends BaseTestCase
{
    public function testThrowsNestingErrorWhenCheckedForLoops()
    {
        $this->expectException(\Icinga\Module\Businessprocess\Exception\NestingError::class);

        /** @var BpNode $bpNode */
        $bpNode = $this->makeLoop()->getNode('d');
        $bpNode->checkForLoops();
    }

    public function testNestingErrorReportsFullLoop()
    {
        $this->expectException(\Icinga\Module\Businessprocess\Exception\NestingError::class);
        $this->expectExceptionMessage('d -> a -> b -> c -> a');

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
