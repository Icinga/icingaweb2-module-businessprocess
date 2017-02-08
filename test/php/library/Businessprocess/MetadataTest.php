<?php

namespace Tests\Icinga\Module\Businessprocess;

use Icinga\Module\Businessprocess\Metadata;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class MetadataTest extends BaseTestCase
{
    public function testDetectsMatchingPrefixes()
    {
        $meta = new Metadata('matchme');
        $this->assertFalse(
            $meta->nameIsPrefixedWithOneOf(array())
        );
        $this->assertFalse(
            $meta->nameIsPrefixedWithOneOf(array('matchr', 'atchme'))
        );
        $this->assertTrue(
            $meta->nameIsPrefixedWithOneOf(array('not', 'mat', 'yes'))
        );
        $this->assertTrue(
            $meta->nameIsPrefixedWithOneOf(array('m'))
        );
        $this->assertTrue(
            $meta->nameIsPrefixedWithOneOf(array('matchme'))
        );
        $this->assertFalse(
            $meta->nameIsPrefixedWithOneOf(array('matchmenot'))
        );
    }
}
