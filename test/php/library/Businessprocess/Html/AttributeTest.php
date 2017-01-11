<?php

namespace Tests\Icinga\Module\Businessprocess\Html;

use Icinga\Module\Businessprocess\Html\Attribute;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class AttributeTest extends BaseTestCase
{
    public function testCreatesFactoryCreatesAttribute()
    {
        $this->assertInstanceOf(
            get_class(new Attribute('a', 'b')),
            Attribute::create('a', 'b')
        );
    }

    public function testKnowsItsName()
    {
        $a = new Attribute('target', '_blank');
        $this->assertEquals(
            'target',
            $a->getName()
        );
    }

    public function testKnowsItsValue()
    {
        $a = new Attribute('target', '_blank');
        $this->assertEquals(
            '_blank',
            $a->getValue()
        );
    }

    public function testItsValueCanBeModified()
    {
        $a = new Attribute('target', '_blank');
        $a->setValue('_self');
        $this->assertEquals(
            '_self',
            $a->getValue()
        );
    }

    public function testPreservesComplexValues()
    {
        $a = new Attribute('special', 'süß "\'&');
        $this->assertEquals(
            'süß "\'&',
            $a->getValue()
        );
    }

    public function testAllowsToExtendItsValue()
    {
        $a = new Attribute('class', 'first');
        $a->addValue('second');

        $this->assertEquals(
            array('first', 'second'),
            $a->getValue()
        );

        $a->addValue('third');

        $this->assertEquals(
            array('first', 'second', 'third'),
            $a->getValue()
        );

        $a->addValue(array('some', 'more'));

        $this->assertEquals(
            array('first', 'second', 'third', 'some', 'more'),
            $a->getValue()
        );
    }

    public function testAcceptsAndReturnsArrayValues()
    {
        $value = array('first', 'second', 'third');
        $a = new Attribute('class', $value);

        $this->assertEquals(
            $value,
            $a->getValue()
        );

        $value[] = 'forth';

        $a->setValue($value);
        $this->assertEquals(
            $value,
            $a->getValue()
        );
    }

    public function testCorrectlyRendersItsName()
    {
        $a = new Attribute('class', 'test');
        $this->assertEquals(
            'class',
            $a->renderName()
        );
    }

    public function testCorrectlyRendersItsValue()
    {
        $a = new Attribute('href', '/some/url?a=b&c=d');
        $this->assertEquals(
            '/some/url?a=b&amp;c=d',
            $a->renderValue()
        );
    }

    public function testCorrectlyRendersArrayValues()
    {
        $a = new Attribute('weird', array('"sü?ß', '/some/url?a=b&c=d'));
        $this->assertEquals(
            '&quot;sü?ß /some/url?a=b&amp;c=d',
            $a->renderValue()
        );
    }

    public function testCorrectlyEscapesAName()
    {
        $this->assertEquals(
            'class',
            Attribute::escapeName('class')
        );
    }

    public function testCorrectlyEscapesAValue()
    {
        $this->assertEquals(
            "&quot;sü?ß' /some/url?a=b&amp;c=d",
            Attribute::escapeValue('"sü?ß\' /some/url?a=b&c=d')
        );
    }

    public function testRendersCorrectly()
    {
        $a = new Attribute('weird', array('"sü?ß', '/some/url?a=b&c=d'));
        $this->assertEquals(
            'weird="&quot;sü?ß /some/url?a=b&amp;c=d"',
            $a->render()
        );
    }
}
