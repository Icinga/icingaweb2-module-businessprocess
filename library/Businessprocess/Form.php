<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Web\Request;
use Icinga\Web\Form as WebForm;

class Form extends WebForm
{
    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->setup();
    }

    public function addHidden($name, $value = null)
    {
        $this->addElement('hidden', $name);
        $this->getElement($name)->setDecorators(array('ViewHelper'));
        if ($value !== null) {
            $this->setDefault($name, $value);
        }
        return $this;
    }

    public function handleRequest(Request $request = null)
    {
        parent::handleRequest();
        return $this;
    }

    public static function construct()
    {
        return new static;
    }
}
