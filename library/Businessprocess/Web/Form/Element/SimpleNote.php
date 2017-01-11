<?php

namespace Icinga\Module\Businessprocess\Web\Form\Element;

class SimpleNote extends FormElement
{
    public $helper = 'formSimpleNote';

    /**
     * Always ignore this element
     * @codingStandardsIgnoreStart
     *
     * @var boolean
     */
    protected $_ignore = true;
    // @codingStandardsIgnoreEnd
    
    public function isValid($value, $context = null)
    {
        return true;
    }
}
