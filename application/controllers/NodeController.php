<?php

use Icinga\Module\Businessprocess\Controller;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\Forms\ProcessForm;
use Icinga\Web\Url;

/*
config = <file>
process = <node>

*/
class Businessprocess_NodeController extends Controller
{
    // rename to config
    public function editAction()
    {
        $bp = $this->loadModifiedBpConfig();
        $node = $bp->getNode($this->getParam('node'));
        $detail = Url::fromPath(
            'businessprocess/node/edit',
            array(
                'config' => $this->view->configName,
                'node'   => $node
            )
        );

        $this->view->form = ProcessForm::construct()
            ->setProcess($bp)
            ->setSession($this->session())
            ->setNode($node)
            ->setRedirectUrl(
                sprintf(
                    'businessprocess/process/show?config=%s&unlocked#!%s',
                    $bp->getName(),
                    $detail->getAbsoluteUrl()
                )
            )
            ->handleRequest();

        $this->view->node = $node;
    }

    public function simulateAction()
    {
        $bp = $this->loadBpConfig();
        $nodename = $this->getParam('node');
        $node = $this->view->node = $bp->getNode($nodename);

        $this->view->form = $this->loadForm('simulation')
             ->setNode($node)
             ->setSimulation(new Simulation($bp, $this->session()))
             ->handleRequest();

        if ($this->view->form->succeeded()) {
            $this->render('empty');
        }
    }

    public function addAction()
    {
        $bp = $this->loadBpConfig();

        $redirectUrl = Url::fromPath(
            'businessprocess/process/show',
            array('config' => $bp->getName())
        );

        $this->view->form = ProcessForm::construct()
            ->setProcess($bp)
            ->setSession($this->session())
            ->setRedirectUrl($redirectUrl)
            ->handleRequest();
    }

    public function deleteAction()
    {
    }
}
