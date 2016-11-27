<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Controller;
use Icinga\Module\Businessprocess\ConfigDiff;
use Icinga\Module\Businessprocess\Renderer\TileRenderer;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\ProcessChanges;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Forms\BpConfigForm;
use Icinga\Module\Businessprocess\Forms\DeleteConfigForm;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Web\Url;
use Icinga\Web\Notification;
use Icinga\Web\Widget\Tabextension\DashboardAction;

class ProcessController extends Controller
{
    protected function currentProcessParams()
    {
        $params = array();
        foreach (array('config', 'node') as $name) {
            if ($value = $this->params->get($name)) {
                $params[$name] = $value;
            }
        }

        return $params;
    }

    /**
     * Create a new business process configuration
     */
    public function createAction()
    {
        $this->assertPermission('businessprocess/create');

        $this->setTitle($this->translate('Create a new business process'));
        $this->tabsForCreate()->activate('create');

        $this->view->form = $this->loadForm('bpConfig')
            ->setStorage($this->storage())
            ->setSuccessUrl('businessprocess/process/show')
            ->handleRequest();
    }

    /**
     * Upload an existing business process configuration
     */
    public function uploadAction()
    {
        $this->setTitle($this->translate('Upload a business process config file'));
        $this->tabsForCreate()->activate('upload');
    }

    /**
     * Show a business process tree
     */
    public function showAction()
    {
        $mode = $this->params->get('mode');
        $unlocked = (bool) $this->params->get('unlocked');

        if ($mode === 'tile') {
            $this->actions()->add(
                Link::create(
                    $this->translate('Tree'),
                    'businessprocess/process/show',
                    $this->currentProcessParams(),
                    array('class' => 'icon-sitemap')
                )
            );
        } else {
            $this->actions()->add(
                Link::create(
                    $this->translate('Tiles'),
                    $this->url()->with('mode', 'tile'),
                    null,
                    array('class' => 'icon-dashboard')
                )
            );
        }

        if ($unlocked) {
            $this->actions()->add(
                Link::create(
                    $this->translate('Lock'),
                    $this->url()->without('unlocked'),
                    null,
                    array(
                        'class' => 'icon-lock',
                        'title' => $this->translate('Lock this process'),
                    )
                )
            );
        } else {
            $this->actions()->add(
                Link::create(
                    $this->translate('Unlock'),
                    $this->url()->with('unlocked', true),
                    null,
                    array(
                        'class' => 'icon-lock-open',
                        'title' => $this->translate('Unlock this process'),
                    )
                )
            );
        }

        $this->actions()->add(
            Link::create(
                $this->translate('Store'),
                'businessprocess/process/config',
                $this->currentProcessParams(),
                array(
                    'class'            => 'icon-wrench',
                    'title'            => $this->translate('Modify this process'),
                    'data-base-target' => '_next',
                )
            )
        );

        $this->prepareProcess();
        $this->redirectOnConfigSwitch();

        if ($unlocked) {
            $bp = $this->loadModifiedBpConfig();
            $bp->unlock();
        } else {
            $bp = $this->loadBpConfig();
        }

        $this->setTitle('Business Process "%s"', $bp->getTitle());
        $this->tabsForShow()->activate('show');

        // Do not lock empty configs
        if ($bp->isEmpty() && ! $this->view->compact && $bp->isLocked()) {
            $this->redirectNow($this->url()->with('unlocked', true));
        }

        if ($node = $this->params->get('node')) {
            // Render a specific node

            $this->view->nodeName = $node;
            $bpNode = $this->view->bp = $bp->getNode($node);
        } else {
            // Render a single process
            $this->view->bp = $bp;
            if ($bp->hasWarnings()) {
                $this->view->warnings = $bp->getWarnings();
            }
            $bpNode = null;
        }

        $bp->retrieveStatesFromBackend();
        if ($this->params->get('addSimulation')) {
            $this->simulationForm();
        }

        // TODO: ...
        $renderer = new TileRenderer($this->view, $bp, $bpNode);
        $renderer->setBaseUrl($this->url())
            ->setPath($this->params->getValues('path'));
        $this->view->bpRenderer = $renderer;

        if ($bp->isLocked()) {
            $this->tabs()->extend(new DashboardAction());
        } else {
            $renderer->unlock();
            $simulation = new Simulation($bp, $this->session());
            if ($this->params->get('dismissSimulations')) {
                Notification::success(
                    sprintf(
                        $this->translate('%d applied simulation(s) have been dropped'),
                        $simulation->count()
                    )
                );
                $simulation->clear();
                $this->redirectNow($this->url()->without('dismissSimulations')->without('unlocked'));
            }

            $bp->applySimulation($simulation);
        }

        if ($this->isXhr()) {
            if ($this->params->get('addSimulation')) {
                $this->setAutorefreshInterval(30);
            } else {
                $this->setAutorefreshInterval(10);
            }
        } else {
            // This will trigger the very first XHR refresh immediately on page
            // load. Please not that this may hammer the server in case we would
            // decide to use autorefreshInterval for HTML meta-refreshes also.
            $this->setAutorefreshInterval(1);
        }

        if ($mode === 'tile') {
            $this->setViewScript('process/bprenderer');
        }
    }

    protected function prepareProcess()
    {
        if ($this->params->get('unlocked')) {
            $bp = $this->loadModifiedBpConfig();
            $bp->unlock();
        } else {
            $bp = $this->loadBpConfig();
        }

        if ($node = $this->params->get('node')) {
            // Render a specific node
            $this->view->nodeName = $node;
            $this->view->bp = $bp->getNode($node);
        }
    }

    protected function simulationForm()
    {
        $this->prepareProcess();
        $bp = $this->loadBpConfig();
        $nodename = $this->getParam('simulationNode');
        $node = $bp->getNode($nodename);

        $url = $this->getRequest()->getUrl()->without('addSimulation')->without('simulationNode');
        $this->view->form = $this->loadForm('simulation')
             ->setSimulation(new Simulation($bp, $this->session()))
             ->setNode($node)
             ->setSuccessUrl($url)
             ->handleRequest();

        $this->view->node = $node;
    }

    /**
     * Show the source code for a process
     */
    public function sourceAction()
    {
        $this->prepareProcess();
        $this->tabsForConfig()->activate('source');
        $bp = $this->loadModifiedBpConfig();

        $this->view->source = $bp->toLegacyConfigString();
        $this->view->showDiff = (bool) $this->params->get('showDiff', false);

        if ($this->view->showDiff) {
            $this->view->diff = ConfigDiff::create(
                $this->storage()->getSource($this->view->configName),
                $this->view->source
            );
            $this->view->title = sprintf(
                $this->translate('%s: Source Code Differences'),
                $bp->getTitle()
            );
        } else {
            $this->view->title = sprintf(
                $this->translate('%s: Source Code'),
                $bp->getTitle()
            );
        }
    }

    /**
     * Download a process configuration file
     */
    public function downloadAction()
    {
        $this->prepareProcess();
        $bp = $this->loadModifiedBpConfig();

        header(
            sprintf(
                'Content-Disposition: attachment; filename="%s.conf";',
                $bp->getName()
            )
        );
        header('Content-Type: text/plain');

        echo $bp->toLegacyConfigString();
        // Didn't have time to lookup how to correctly disable our renderers
        // TODO: no exit :)
        $this->doNotRender();
    }

    /**
     * Modify a business process configuration
     */
    public function configAction()
    {
        $this->prepareProcess();
        $this->tabsForConfig()->activate('config');
        $bp = $this->loadModifiedBpConfig();

        $this->setTitle(
            $this->translate('%s: Configuration'),
            $bp->getTitle()
        );

        $url = Url::fromPath(
            'businessprocess/process/show?unlocked',
            array('config' => $bp->getName())
        );

        $this->view->form = $this->loadForm('bpConfig')
            ->setProcessConfig($bp)
            ->setStorage($this->storage())
            ->setSuccessUrl($url)
            ->handleRequest();
    }

    /**
     * Redirect to our URL plus the chosen config if someone switched the
     * config in the appropriate dropdown list
     */
    protected function redirectOnConfigSwitch()
    {
        $request = $this->getRequest();
        if ($request->isPost() && $request->getPost('action') === 'switchConfig') {
            // We switched the process in the config dropdown list
            $params = array(
                'config' => $request->getPost('config')
            );
            $this->redirectNow($this->url()->with($params));
        }
    }

    protected function tabsForShow()
    {
        return $this->tabs()->add('show', array(
            'label' => $this->translate('Business Process'),
            'url'   => $this->url()
        ));
    }

    protected function tabsForCreate()
    {
        return $this->tabs()->add('create', array(
            'label' => $this->translate('Create'),
            'url'   => 'businessprocess/process/create'
        ))->add('upload', array(
            'label' => $this->translate('Upload'),
            'url'   => 'businessprocess/process/upload'
        ));
    }

    protected function tabsForConfig()
    {
        return $this->tabs()->add('config', array(
            'label' => $this->translate('Process Configuration'),
            'url'   => $this->getRequest()->getUrl()->without('nix')->setPath('businessprocess/process/config')
        ))->add('source', array(
            'label' => $this->translate('Source'),
            'url'   => $this->getRequest()->getUrl()->without('nix')->setPath('businessprocess/process/source')
        ));
    }
}
