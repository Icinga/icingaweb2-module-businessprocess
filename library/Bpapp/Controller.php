<?php

namespace Icinga\Module\Bpapp;

use Icinga\Web\Controller\ModuleActionController;
use Icinga\Module\Monitoring\Backend;
use Icinga\Module\Bpapp\Storage\LegacyStorage;
use Icinga\Module\Bpapp\BusinessProcess;
use Icinga\Module\Bpapp\Form\ProcessForm;
use Icinga\Module\Bpapp\Form\SimulationForm;
use Icinga\Web\Url;
use Icinga\Web\Widget;

class Controller extends ModuleActionController
{
    protected $config;

    protected $backend;

    protected $views;

    protected $aliases;

    public function init()
    {
        $this->config = $this->Config();
        $this->readConfig();
        $this->prepareBackend();
    }

    protected function tabs()
    {
        $url = Url::fromRequest();
        $tabs = Widget::create('tabs')->add('show', array(
            'title' => $this->translate('Show'),
            'url' => 'bpapp/process/show',
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
        return $this->Window()->getSessionNamespace('bpapp');
    }

    protected function xxxloadBp()
    {
        $bpconf = $this->bpconf;
        $bp = BusinessProcess::parse($this->filename);

        if ($this->_getParam('state_type') === 'soft'
           || (isset($bpconf->states) && $bpconf->states === 'soft')) {
            $bp->useSoftStates();
        }
        $bp->retrieveStatesFromBackend($this->backend);
        return $bp;
    }
    protected function loadBp()
    {
        $storage = new LegacyStorage($this->Config()->getSection('global'));
        $this->view->processList = $storage->listProcesses();
        $process = $this->params->get('processName', key($this->view->processList));
        $this->view->processName = $process;

        $bp = $storage->loadProcess($process);

        if ($this->_getParam('state_type') === 'soft'
           || (isset($bpconf->states) && $bpconf->states === 'soft')) {
            $bp->useSoftStates();
        }
        //$bp->retrieveStatesFromBackend($this->backend);
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

    protected function readConfig()
    {
        $this->views = array();
        $this->aliases = array();
        foreach ($this->config->keys() as $key) {
            if (! preg_match('~^view-(.+)$~', $key, $match)) continue;
            $conf = $this->config->getSection($key);
            $this->views[$match[1]] = (object) $conf->toArray();
            $this->aliases[(string) $conf->title] = $match[1];
            if ($conf->aliases) {
                foreach (preg_split('~\s*,\s*~', $conf->aliases, -1, PREG_SPLIT_NO_EMPTY) as $alias) {
                    $this->aliases[$alias] = $match[1];
                }
            }
        }

        $bpname = $this->_getParam('bp', key($this->views));

        if (array_key_exists($bpname, $this->aliases)) {
            $bpname = $this->aliases[$bpname];
        }
        if (! array_key_exists($bpname, $this->views)) {
            throw new Exception('Got invalid bp name: ' . $bpname);
        }
        $this->bpconf = $this->views[$bpname];

        $this->bpname = $bpname;

        $this->filename = $this->config->get('global', 'bp_config_dir')
            . '/' . $this->bpconf->file . '.conf';
    }

    protected function prepareBackend()
    {
        if ($this->backend === null) {
            $name = $this->config->get('global', 'default_backend');
            if (isset($this->bpconf->backend)) {
                $name = $this->bpconf->backend;
            }

            $this->backend = Backend::createBackend($name);
        }
        return $this->backend;
    }
}
