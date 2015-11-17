<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Controller;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\Forms\ProcessForm;
use Icinga\Module\Businessprocess\Forms\SimulationForm;
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
        $node = $bp->getNode($nodename);
        $details = Url::fromPath(
            'businessprocess/node/simulate',
            array(
                'config' => $this->view->configName,
                'node'   => $nodename
            )
        );
        $url = sprintf(
            'businessprocess/process/show?unlocked&config=%s#!%s',
            $bp->getName(),
            $details->getAbsoluteUrl()
        );

        $this->view->form = SimulationForm::construct()
             ->setSimulation(new Simulation($bp, $this->session()))
             ->setNode($node)
              // TODO: find a better way to handle redirects
             ->setRedirectUrl($url)
             ->handleRequest();
        $this->view->node = $node;
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
