<?php

namespace Icinga\Module\Businessprocess\Web\Form\Element;

use Zend_Form_Element_Multiselect;

class Multiselect extends Zend_Form_Element_Multiselect
{
    protected function _getErrorMessages()
    {
        // The base implementation is prone to message duplication in case of custom error messages.
        // Since its actual behavior is not required it's entirely bypassed.
        return $this->getErrorMessages();
    }
}
