<?php

namespace Icinga\Module\Businessprocess\Web\Form\Validator;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Forms\EditNodeForm;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Zend_Validate_Abstract;

class NoDuplicateChildrenValidator extends Zend_Validate_Abstract
{
    const CHILD_FOUND = 'childFound';

    /** @var QuickForm */
    protected $form;

    /** @var BpConfig */
    protected $bp;

    /** @var BpNode */
    protected $parent;

    /** @var string */
    protected $label;

    public function __construct(QuickForm $form, BpConfig $bp, BpNode $parent = null)
    {
        $this->form = $form;
        $this->bp = $bp;
        $this->parent = $parent;

        $this->_messageVariables['label'] = 'label';
        $this->_messageTemplates = [
            self::CHILD_FOUND => mt('businessprocess', '%label% is already defined in this process')
        ];
    }

    public function isValid($value)
    {
        if ($this->parent === null) {
            $found = $this->bp->hasRootNode($value);
        } elseif ($this->form instanceof EditNodeForm && $this->form->getNode()->getName() === $value) {
            $found = false;
        } else {
            $found = $this->parent->hasChild($value);
        }

        if (! $found) {
            return true;
        }

        $this->label = $this->form->getElement('children')->getMultiOptions()[$value];
        $this->_error(self::CHILD_FOUND);
        return false;
    }
}
