<?php

use Icinga\Module\Businessprocess\Controller;
use Icinga\Module\Businessprocess\Forms\ProcessForm;
use Icinga\Module\Businessprocess\Forms\SimulationForm;
use Icinga\Web\Url;

class Businessprocess_NodeController extends Controller
{
    public function editAction()
    {
        $bp = $this->loadBp();
        $node = $bp->getNode($this->getParam('node'));

        $form = new ProcessForm();
        $form->setBackend($this->backend)
              ->setProcess($bp)
              ->setNode($node)
              ->handleRequest();

        $this->view->form = $form;
        $this->view->node = $node;
    }

    public function simulateAction()
    {
        $bp = $this->loadBp();
        $nodename = $this->getParam('node');
        $node = $bp->getNode($nodename);
        $detail = Url::fromPath(
            'businessprocess/node/simulate',
            array('node' => $nodename)
        );
        $form = new SimulationForm();

        $form->setBackend($this->backend)
              ->setProcess($bp)
              ->setSession($this->session())
              ->setNode($node)
               // TODO: find a better way to handle redirects
              ->setRedirectUrl(
                  sprintf(
                      'businessprocess/process/show?simulation=1&processName=%s#!%s',
                      $bp->getName(),
                      $detail->getAbsoluteUrl()
                  )
              )
              ->handleRequest();

        $this->view->form = $form;
        $this->view->node = $node;
    }
}
