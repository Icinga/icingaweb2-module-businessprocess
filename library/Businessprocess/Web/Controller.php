<?php

namespace Icinga\Module\Businessprocess\Web;

use Icinga\Application\Icinga;
use Icinga\Module\Businessprocess\BpConfig;
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
use ipl\Html\Html;

class Controller extends ModuleController
{
    /** @var View */
    public $view;

    /** @var BpConfig */
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
        $this->controls();
        $this->content();
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
            $controls = $this->view->controls = new Controls();
            if ($this->view->compact) {
                $controls->getAttributes()->add('class', 'compact');
            }
        }

        return $this->view->controls;
    }

    /**
     * @return Content
     */
    protected function content()
    {
        if ($this->view->content === null) {
            $content = $this->view->content = new Content();
            if ($this->view->compact) {
                $content->getAttributes()->add('class', 'compact');
            }
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

    protected function addTitle($title)
    {
        $args = func_get_args();
        array_shift($args);
        $this->view->title = vsprintf($title, $args);
        $this->controls()->add(Html::tag('h1', null, $this->view->title));
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
            $this->storage = LegacyStorage::getInstance();
        }

        return $this->storage;
    }
}
