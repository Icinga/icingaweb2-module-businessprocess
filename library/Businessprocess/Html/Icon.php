<?php

namespace Icinga\Module\Businessprocess\Html;

class Icon extends BaseElement
{
    protected $tag = 'i';

    protected function __construct()
    {
    }

    /**
     * @param string $name
     * @param array $attributes
     *
     * @return static
     */
    public static function create($name, array $attributes = null)
    {
        $icon = new static();
        $icon->setAttributes($attributes);
        $icon->attributes()->add('class', array('icon', 'icon-' . $name));
        return $icon;
    }
}
