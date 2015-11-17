<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Controller;
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

    public function simulateAction()
    {
        $bp = $this->loadBpConfig();
        $nodename = $this->getParam('node');
        $node = $bp->getNode($nodename);
        $url = Url::fromPath(
            'businessprocess/process/show?unlocked',
            array('config' => $bp->getName())
        );

        $this->view->form = $this->loadForm('simulation')
             ->setSimulation(new Simulation($bp, $this->session()))
             ->setNode($node)
             ->setSuccessUrl($url)
             ->handleRequest();

        $this->view->node = $node;
    }

    public function addAction()
    {
        $bp = $this->loadBpConfig();

        $url = Url::fromPath(
            'businessprocess/process/show',
            array('config' => $bp->getName())
        );

        $this->view->form = $this->loadForm('process')
            ->setProcess($bp)
            ->setSession($this->session())
            ->setRedirectUrl($url)
            ->handleRequest();
    }

    public function deleteAction()
    {
    }
}
