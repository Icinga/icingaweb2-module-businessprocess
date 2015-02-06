<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Application\Icinga;
use Icinga\Web\Controller\ModuleActionController;
use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Form\ProcessForm;
use Icinga\Module\Businessprocess\Form\SimulationForm;
use Icinga\Web\Url;
use Icinga\Web\Widget;
use Exception;

class Controller extends ModuleActionController
{
    protected $config;

    protected $backend;

    protected $views;

    protected $aliases;

    public function init()
    {
        $m = Icinga::app()->getModuleManager();
        if (! $m->hasLoaded('monitoring') && $m->hasInstalled('monitoring')) {
            $m->loadModule('monitoring');
        }

        $this->config = $this->Config();
    }

    protected function tabs()
    {
        $url = Url::fromRequest();
        $tabs = Widget::create('tabs')->add('show', array(
            'title' => $this->translate('Show'),
            'url' => 'businessprocess/process/show',
        ));
        if ($process = $this->params->get('process')) {
            foreach ($tabs->getTabs() as $tab) {
                $tab->setUrlParams(array('process' => $process));
            }
        }
        return $tabs;
    }

    protected function session()
    {
        return $this->Window()->getSessionNamespace('businessprocess');
    }

    protected function loadBp()
    {
        $storage = new LegacyStorage($this->Config()->getSection('global'));
        $this->view->processList = $storage->listProcesses();
        $process = $this->params->get('processName', key($this->view->processList));
        $this->view->processName = $process;

        $bp = $storage->loadProcess($process);

        if (null !== ($stateType = $this->params->get('state_type'))) {
            if ($stateType === 'soft') {
                $bp->useSoftStates();
            }
            if ($stateType === 'soft') {
                $bp->useHardStates();
            }
        }

        $bp->retrieveStatesFromBackend();
        return $bp;
    }

    protected function loadSlas()
    {
        $bpconf = $this->bpconf;

        if (! isset($bpconf->slahosts)) {
            return array();
        }

        // TODO: This doesn't work right now
        $sla_hosts = preg_split('~\s*,s*~', $bpconf->slahosts, -1, PREG_SPLIT_NO_EMPTY);

        if (isset($bpconf->sla_year)) {
            $start =  mktime(0, 0, 0, 1, 1, $bpconf->sla_year);
            $end =  mktime(23, 59, 59, 1, 0, $bpconf->sla_year + 1);
        } else {
            $start =  mktime(0, 0, 0, 1, 1, (int) date('Y'));
            $end = null;
            // Bis zum Jahresende hochrechnen:
            // $end =  mktime(23, 59, 59, 1, 0, (int) date('Y') + 1);
        }

        return $this->backend
            ->module('BpAddon')
            ->getBpSlaValues($sla_hosts, $start, $end);
    }
}
