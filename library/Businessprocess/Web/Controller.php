<?php

namespace Icinga\Module\Businessprocess\Web;

use Icinga\Application\Icinga;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Storage\Storage;
use Icinga\Module\Businessprocess\Web\Component\ActionBar;
use Icinga\Module\Businessprocess\Web\Component\Controls;
use Icinga\Module\Businessprocess\Web\Component\Content;
use Icinga\Module\Businessprocess\Web\Component\Tabs;
use Icinga\Module\Businessprocess\Web\Form\FormLoader;
use Icinga\Web\Controller as ModuleController;
use Icinga\Web\Notification;
use Icinga\Web\View;

class Controller extends ModuleController
{
    /** @var View */
    public $view;

    /** @deprecated, obsolete */
    protected $backend;

    /** @var BusinessProcess */
    protected $bp;

    /** @var Tabs */
    protected $mytabs;

    /** @var Storage */
    private $storage;

    /** @var bool */
    protected $showFullscreen;

    /** @var Url */
    private $url;

    public function init()
    {
        $m = Icinga::app()->getModuleManager();
        if (! $m->hasLoaded('monitoring') && $m->hasInstalled('monitoring')) {
            $m->loadModule('monitoring');
        }
        $this->view->errors = array();

        $this->url();
        $this->view->showFullscreen
            = $this->showFullscreen
            = (bool) $this->_helper->layout()->showFullscreen;

        $this->view->compact = $this->params->get('view') === 'compact';
        $this->setViewScript('default');
    }

    /**
     * @return Url
     */
    protected function url()
    {
        if ($this->url === null) {
            $this->url = Url::fromPath(
                $this->getRequest()->getUrl()->getPath()
            )->setParams($this->params);
        }

        return $this->url;
    }

    /**
     * @return ActionBar
     */
    protected function actions()
    {
        if ($this->view->actions === null) {
            $this->view->actions = new ActionBar();
        }

        return $this->view->actions;
    }

    /**
     * @return Controls
     */
    protected function controls()
    {
        if ($this->view->controls === null) {
            $this->view->controls = Controls::create();
        }

        return $this->view->controls;
    }

    /**
     * @return Content
     */
    protected function content()
    {
        if ($this->view->content === null) {
            $this->view->content = Content::create();
        }

        return $this->view->content;
    }

    /**
     * @param $label
     * @return Tabs
     */
    protected function singleTab($label)
    {
        return $this->tabs()->add(
            'tab',
            array(
                'label' => $label,
                'url'   => $this->getRequest()->getUrl()
            )
        )->activate('tab');
    }

    /**
     * @return Tabs
     */
    protected function defaultTab()
    {
        return $this->singleTab($this->translate('Business Process'));
    }

    /**
     * @return Tabs
     */
    protected function overviewTab()
    {
        return $this->tabs()->add(
            'overview',
            array(
                'label' => $this->translate('Business Process'),
                'url'   => 'businessprocess'
            )
        )->activate('overview');
    }

    /**
     * @return Tabs
     */
    protected function tabs()
    {
        // Todo: do not add to view once all of them render controls()
        if ($this->mytabs === null) {
            $tabs = new Tabs();
            //$this->controls()->add($tabs);
            $this->mytabs = $tabs;
        }

        return $this->mytabs;
    }

    protected function session()
    {
        return $this->Window()->getSessionNamespace('businessprocess');
    }

    protected function setViewScript($name)
    {
        $this->_helper->viewRenderer->setNoController(true);
        $this->_helper->viewRenderer->setScriptAction($name);
        return $this;
    }

    protected function setTitle($title)
    {
        $args = func_get_args();
        array_shift($args);
        $this->view->title = vsprintf($title, $args);
        return $this;
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

    protected function doNotRender()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        return $this;
    }

    protected function loadBpConfig()
    {
        $name = $this->params->get('config');
        $storage = $this->storage();

        if (! $storage->hasProcess($name)) {
            $this->httpNotFound(
                $this->translate('No such process config: "%s"'),
                $name
            );
        }

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

        $this->view->bpconfig = $this->bp = $bp;
        $this->view->configName = $bp->getName();

        return $bp;
    }

    public function loadForm($name)
    {
        return FormLoader::load($name, $this->Module());
    }

    /**
     * @return LegacyStorage|Storage
     */
    protected function storage()
    {
        if ($this->storage === null) {
            $this->storage = new LegacyStorage(
                $this->Config()->getSection('global')
            );
        }

        return $this->storage;
    }

    /**
     * @deprecated
     */
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
