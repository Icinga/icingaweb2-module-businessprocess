<?php

use Icinga\Web\Controller\ModuleActionController;

class Bpapp_TestController extends ModuleActionController
{
    public function layoutAction()
    {
        $this->view->title = 'Testlayout';
    }
}

