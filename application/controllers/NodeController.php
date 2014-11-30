<?php

use Icinga\Module\Bpapp\Controller;
use Icinga\Module\Bpapp\Forms\ProcessForm;
use Icinga\Module\Bpapp\Forms\SimulationForm;
use Icinga\Web\Url;

class Bpapp_NodeController extends Controller
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
            'bpapp/node/simulate',
            array('node' => $nodename)
        );
        $form = new SimulationForm();

        $form->setBackend($this->backend)
              ->setProcess($bp)
              ->setSession($this->session())
              ->setNode($node)
               // TODO: find a better way to handle redirects
              ->setRedirectUrl('bpapp/process/simulate#!' . $detail->getAbsoluteUrl())
              ->handleRequest();

        $this->view->form = $form;
        $this->view->node = $node;
    }
}
