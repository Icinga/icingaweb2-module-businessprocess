<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Web\Url;

/*
config = <file>
process = <node>

*/
class NodeController extends Controller
{
    // rename to config
    public function editAction()
    {
        $bp = $this->loadModifiedBpConfig();
        $node = $bp->getNode($this->getParam('node'));
        $url = Url::fromPath(
            'businessprocess/process/show?unlocked',
            array('config' => $bp->getName())
        );

        $this->view->form = $this->loadForm('process')
            ->setProcess($bp)
            ->setSession($this->session())
            ->setNode($node)
            ->setSuccessUrl($url)
            ->handleRequest();

        $this->view->node = $node;
    }
}
