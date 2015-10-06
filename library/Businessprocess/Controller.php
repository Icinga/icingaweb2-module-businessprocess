<?php

namespace Icinga\Module\Businessprocess;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Form\ProcessForm;
use Icinga\Module\Businessprocess\Form\SimulationForm;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Monitoring\Backend;
use Icinga\Web\Controller as ModuleController;
use Icinga\Web\Notification;
use Icinga\Module\Director\Web\Form\FormLoader;
use Icinga\Web\Url;
use Icinga\Web\Widget;

class Controller extends ModuleController
{
    protected $config;

    protected $backend;

    private $storage;

    private $url;

    public function init()
    {
        $m = Icinga::app()->getModuleManager();
        if (! $m->hasLoaded('monitoring') && $m->hasInstalled('monitoring')) {
            $m->loadModule('monitoring');
        }
        $this->view->errors = array();

        $this->view->compact = $this->params->get('view') === 'compact';
    }

    protected function url()
    {
        if ($this->url === null) {
            $this->url = clone $this->getRequest()->getUrl();
        }
        return $this->url;
    }

    protected function tabs()
    {
        if ($this->view->tabs === null) {
            $this->view->tabs = Widget::create('tabs');
        }
        return $this->view->tabs;
    }

    protected function session()
    {
        return $this->Window()->getSessionNamespace('businessprocess');
    }

    protected function setTitle($title)
    {
        $args = func_get_args();
        array_shift($args);
        $this->view->title = vsprintf($title, $args);
    }

    protected function loadModifiedBpConfig()
    {
        $bp = $this->loadBpConfig();
        $changes = ProcessChanges::construct($bp, $this->session());
        if ($this->params->get('dismissChanges')) {
            Notification::success(
                sprintf(
                    $this->translate('%d pending change(s) have been dropped'),
                    $changes->count()
                )
            );
            $changes->clear();
            $this->redirectNow($this->url()->without('dismissChanges')->without('unlocked'));
        }
        $bp->applyChanges($changes);
        return $bp;
    }

    protected function loadBpConfig()
    {
        $storage = $this->storage();
        $this->view->processList = $storage->listProcesses();

        // No process found? Go to welcome page
        if (empty($this->view->processList)) {
            $this->redirectNow('businessprocess');
        }

        $name = $this->params->get(
            'config',
            key($this->view->processList)
        );

        $modifications = $this->session()->get('modifications', array());
        if (array_key_exists($name, $modifications)) {
            $bp = $storage->loadFromString($name, $modifications[$name]);
        } else {
            $bp = $storage->loadProcess($name);
        }

        // allow URL parameter to override configured state type
        if (null !== ($stateType = $this->params->get('state_type'))) {
            if ($stateType === 'soft') {
                $bp->useSoftStates();
            }
            if ($stateType === 'hard') {
                $bp->useHardStates();
            }
        }

        $this->view->bpconfig = $bp;
        $this->view->configName = $bp->getName();

        return $bp;
    }

    public function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }

    protected function storage()
    {
        if ($this->storage === null) {
            $this->storage = new LegacyStorage(
                $this->Config()->getSection('global')
            );
        }

        return $this->storage;
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
