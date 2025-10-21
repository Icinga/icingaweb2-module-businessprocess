<?php

namespace Icinga\Module\Businessprocess\Web\Form\Element;

class SimpleNote extends FormElement
{
    public $helper = 'formSimpleNote';

    /**
     * Always ignore this element
     *
     * @var boolean
     */
    protected $_ignore = true; // phpcs:ignore

    public function isValid($value, $context = null)
    {
        return true;
    }
}
