<?php

use Icinga\Module\Businessprocess\Controller;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class Businessprocess_ProcessController extends Controller
{
    public function showAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->redirectNow($this->getRequest()->getUrl()->with('processName', $this->getRequest()->getPost('processName')));
        }
        $this->view->compact = $this->params->get('view') === 'compact';
        $storage = new LegacyStorage($this->Config()->getSection('global'));
        $this->view->processList = $storage->listProcesses();
        $process = $this->params->get('processName', key($this->view->processList));
        $this->view->processName = $process;

        $this->view->tabs = $this->tabs()->activate('show');
        $this->view->title = 'Business Processes';
        $bp = $this->loadBp()->retrieveStatesFromBackend();
        if ($process = $this->params->get('process')) {
            $this->view->bp = $bp->getNode($process);
        } else {
            $this->view->bp = $bp;
            if ($bp->hasWarnings()) {
                $this->view->warnings = $bp->getWarnings();
            }
        }
        $this->setAutorefreshInterval(10);

        if ($this->params->get('showSource')) {
            $this->view->source = $bp->toLegacyConfigString();
            $this->render('source');
        }
        if ($this->params->get('simulation')) {
            $bp->setSimulationMode();
            $this->addSimulation($bp);
        }

        if ($this->params->get('edit')) {
            $bp->setEditMode();
        }

        if ($this->params->get('store')) {
            $storage->storeProcess($bp);
            $this->redirectNow($this->getRequest()->getUrl()->without('store'));
        }

        if ($this->params->get('mode') === 'toplevel') {
            $this->render('toplevel');
        }

    }

    protected function addSimulation($bp)
    {
        $simulations = $this->session()->get('simulations', array());
        foreach ($simulations as $node => $s) {
            $bp->getNode($node)
               ->setState($s->state)
               ->setAck($s->acknowledged)
               ->setDowntime($s->in_downtime);
        }
    }
}
