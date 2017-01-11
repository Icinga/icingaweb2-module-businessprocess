<?php

namespace Tests\Icinga\Module\Businessprocess\Html;

use Icinga\Module\Businessprocess\Html\Attribute;
use Icinga\Module\Businessprocess\Html\Attributes;
use Icinga\Module\Businessprocess\Test\BaseTestCase;

class AttributesTest extends BaseTestCase
{
    public function testCanBeConstructedFromANormalArray()
    {
        $a = new Attributes(
            array(
                'class'  => array('small', 'nice'),
                'target' => '_blank'
            )
        );

        $this->assertEquals(
            ' class="small nice" target="_blank"',
            $a->render()
        );
    }

    public function testCanBeInstantiatedThroughCreate()
    {
        $class = get_class(new Attributes());

        $this->assertInstanceOf(
            $class,
            Attributes::create()
        );

        $this->assertInstanceOf(
            $class,
            Attributes::create(array('some' => 'attr'))
        );
    }

    public function testCanBeCreatedForArrayOrNullOrAttributes()
    {
        $empty = Attributes::wantAttributes(null);
        $this->assertEquals('', $empty->render());

        $array = Attributes::wantAttributes(array('a' => 'b'));
        $this->assertEquals(' a="b"', $array->render());

        $attributes = Attributes::wantAttributes(
            Attributes::create(array('a' => 'b'))
        );
        $this->assertEquals(' a="b"', $attributes->render());
    }

    public function testCanBeExtendedWithAnAttribute()
    {
        $a = Attributes::create();
        $a->add(Attribute::create('a', 'b'));
        $this->assertEquals(' a="b"', $a->render());

        $a->add(Attribute::create('c', 'd'));
        $this->assertEquals(' a="b" c="d"', $a->render());

        $a->add(Attribute::create('a', 'c'));
        $this->assertEquals(' a="b c" c="d"', $a->render());
    }

    public function testCanBeExtendedWithAttributes()
    {
        $a = Attributes::create();
        $a->add(Attributes::create(array('a' => 'b')));
        $this->assertEquals(' a="b"', $a->render());

        $a->add(Attributes::create(
            array(
                'a' => 'c',
                'c' => 'd'
            )
        ));
        $this->assertEquals(' a="b c" c="d"', $a->render());
    }

    public function testCanBeExtendedWithANameValuePair()
    {
        $a = Attributes::create();
        $a->add('a', 'b');
        $this->assertEquals(' a="b"', $a->render());
    }

    public function testAttributesCanBeReplacedWithAnAttribute()
    {
        $a = Attributes::create();
        $a->add(Attribute::create('a', 'b'));
        $a->set(Attribute::create('a', 'c'));
        $this->assertEquals(' a="c"', $a->render());
    }

    public function testAttributesCanBeReplacedWithANameValuePair()
    {
        $a = Attributes::create();
        $a->add(Attribute::create('a', 'b'));
        $a->set('a', 'c');
        $this->assertEquals(' a="c"', $a->render());
    }

    public function testCanBeConstructedAndRenderedEmpty()
    {
        $a = new Attributes();
        $this->assertEquals('', $a->render());
    }
}
