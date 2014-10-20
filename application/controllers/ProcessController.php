<?php

use Icinga\Module\Monitoring\Backend;
use Icinga\Web\Controller\ModuleActionController;
use Icinga\Module\Bpapp\BusinessProcess;
use Icinga\Application\Config;
use Icinga\Web\Widget;

class Bpapp_ProcessController extends ModuleActionController
{
    protected $backend;
    protected $views;
    protected $aliases;
    protected $bpname;
    protected $bpconf;
    protected $filename;

    public function init()
    {
        // $this->requireJs('bpaddon.js');
        $this->config = Config::module('bpapp');
        $this->readConfig();
        $this->prepareBackend();
        $this->view->showMenu = $this->_getParam('menu', 'enabled') === 'enabled';
        $this->view->tabs = $this->createTabs();
    }

    protected function prepareBackend()
    {
        if ($this->backend === null) {
            $name = $this->config->{'global'}->default_backend;
            if (isset($this->bpconf->backend)) {
                $name = $this->bpconf->backend;
            }
            $this->view->backend = $name;
            $this->backend = Backend::createBackend($name);
        }
        return $this->backend;
    }

    protected function createTabs()
    {
        // $tabs = $this->widget('tabs');
        $tabs = Widget::create('tabs');
        $action = $this->_request->getActionName();
        foreach ($this->views as $bpname => $bpconf) {
            $tabs->add($bpname, array(
                'url'       => 'bpapp/process/' . $action,
                'urlParams' => array('bp' => $bpname),
                'title' => $bpconf->title,    
            ));
        }
        $tabs->activate($this->bpname);
        return $tabs;
    }

    protected function readConfig()
    {
        $this->views = array();
        $this->aliases = array();
        foreach ($this->config as $key => $val) {
            if (! preg_match('~^view-(.+)$~', $key, $match)) continue;
            $this->views[$match[1]] = (object) $val->toArray();
            $this->aliases[(string) $val->title] = $match[1];
            if ($val->aliases) {
                foreach (preg_split('~\s*,\s*~', $val->aliases, -1, PREG_SPLIT_NO_EMPTY) as $alias) {
                    $this->aliases[$alias] = $match[1];
                }
            }
        }
        $this->view->views = $this->views;
        $bpname = $this->_getParam('bp', key($this->views));

        if (array_key_exists($bpname, $this->aliases)) {
            $bpname = $this->aliases[$bpname];
        }
        if (! array_key_exists($bpname, $this->views)) {
            throw new Exception('Got invalid bp name: ' . $bpname);
        }
        $this->bpconf = $this->views[$bpname];
        $this->view->bpname = $bpname;
        $this->bpname = $bpname;

        $this->filename = $this->config->global->bp_config_dir
            . '/' . $this->bpconf->file . '.conf';
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

    public function showAction()
    {
        $this->setAutoRefreshInterval(10);

        $this->view->opened  = $this->_getParam('opened');
        $this->view->compact  = $this->_getParam('view') === 'compact';
        $bpconf = $this->bpconf;
        $this->view->title = 'Process: ' . $bpconf->title;

        $bp = BusinessProcess::parse($this->filename);
        if ($this->_getParam('state_type') === 'soft'
           || (isset($bpconf->states) && $bpconf->states === 'soft')) {
            $bp->useSoftStates();
        }
        $bp->retrieveStatesFromBackend($this->backend);
        $this->view->bp = $bp;

        if (isset($bpconf->slahosts)) {
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
            $this->view->slas = $this->backend
                ->module('BpAddon')
                ->getBpSlaValues($sla_hosts, $start, $end);
        } else {
            $this->view->slas = array();
        }
        
        $this->view->available_bps = $this->views;
    }
}

