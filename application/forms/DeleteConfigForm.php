<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Form;
use Icinga\Web\Notification;

class DeleteConfigForm extends Form
{
    protected $storage;

    protected $controller;

    public function setup()
    {
        $this->addHidden('name');
        $this->addElement('submit', $this->translate('Delete this process'));
    }

    public function setStorage($storage)
    {
        $this->storage = $storage;
        return $this;
    }

    public function setController($controller)
    {
        $this->controller = $controller;
        return $this;
    }

    public function setBpConfig($bp)
    {
        $this->getElement('name')->setValue($bp->getName());
        return $this;
    }

    public function onSuccess()
    {
        $name = $this->getValue('name');
        $this->storage->deleteProcess($name);
        $this->setRedirectUrl('businessprocess');
        Notification::success(sprintf('Process %s has been deleted', $name));
    }
}
