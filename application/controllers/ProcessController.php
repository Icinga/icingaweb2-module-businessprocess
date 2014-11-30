<?php

use Icinga\Module\Bpapp\Controller;
use Icinga\Module\Bpapp\Storage\LegacyStorage;

class Bpapp_ProcessController extends Controller
{
    public function showAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->redirectNow($this->getRequest()->getUrl()->with('processName', $this->getRequest()->getPost('processName')));
        }
        $storage = new LegacyStorage($this->Config()->getSection('global'));
        $this->view->processList = $storage->listProcesses();
        $process = $this->params->get('processName', key($this->view->processList));
        $this->view->processName = $process;

        $this->view->tabs = $this->tabs()->activate('show');
        $this->view->title = 'Business Processes';
        $bp = $this->loadBp();
        if ($process = $this->params->get('process')) {
            $this->view->bp = $bp->getNode($process);
        } else {
            $this->view->bp = $bp;
            if ($bp->hasWarnings()) {
                $this->view->warnings = $bp->getWarings();
            }
        }
        $this->setAutorefreshInterval(10);

        if ($this->params->get('simulation')) {
            $bp->setSimulationMode();
            $this->addSimulation($bp);
        }

        if ($this->params->get('edit')) {
            $bp->setEditMode();
        }

    }

    public function sourceAction()
    {
        $this->view->title = 'Source: ' . $this->bpconf->title;
        $this->view->source = file_get_contents($this->filename);
    }


    public function historyAction()
    {
        $bp = BusinessProcess::parse($this->filename);
        echo '<pre>' . print_r($bp, 1) . '</pre>';
        exit;
    }
}