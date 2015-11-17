<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Controller;
use Icinga\Module\Businessprocess\ConfigDiff;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\ProcessChanges;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Forms\BpConfigForm;
use Icinga\Module\Businessprocess\Forms\DeleteConfigForm;
use Icinga\Web\Notification;
use Icinga\Web\Widget\Tabextension\DashboardAction;


class ProcessController extends Controller
{
    /**
     * Create a new business process configuration
     */
    public function createAction()
    {
        $this->setTitle($this->translate('Create a new business process'));
        $this->tabsForCreate()->activate('create');

        $this->view->form = BpConfigForm::construct()
            ->setStorage($this->storage())
            ->setRedirectUrl('businessprocess/process/show')
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
        $this->redirectOnConfigSwitch();

        if ($this->params->get('unlocked')) {
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
            $this->view->bp = $bp->getNode($node);
        } else {
            // Render a single process
            $this->view->bp = $bp;
            if ($bp->hasWarnings()) {
                $this->view->warnings = $bp->getWarnings();
            }
        }

        $bp->retrieveStatesFromBackend();

        if ($bp->isLocked()) {
            $this->tabs()->extend(new DashboardAction());
        } else {
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
            $this->setAutorefreshInterval(10);
        } else {
            // This will trigger the very first XHR refresh immediately on page
            // load. Please not that this may hammer the server in case we would
            // decide to use autorefreshInterval for HTML meta-refreshes also.
            $this->setAutorefreshInterval(1);
        }

        if ($this->params->get('mode') === 'toplevel') {
            $this->render('toplevel');
        }
    }

    /**
     * Show the source code for a process
     */
    public function sourceAction()
    {
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
        $this->tabsForConfig()->activate('config');
        $bp = $this->loadModifiedBpConfig();

        $this->setTitle(
            $this->translate('%s: Configuration'),
            $bp->getTitle()
        );

        $url = sprintf(
            'businessprocess/process/show?config=%s&unlocked#!%s',
            $bp->getName(),
            $this->getRequest()->getUrl()
        );
        $this->view->form = BpConfigForm::construct()
            ->setProcessConfig($bp)
            ->setStorage($this->storage())
            ->setRedirectUrl($url)
            ->handleRequest();

        $this->view->deleteForm = DeleteConfigForm::construct()
            ->setStorage($this->storage())
            ->setController($this)
            ->setBpConfig($bp)
            ->handleRequest();
    }

    /**
     * Redirect to our URL plus the chosen config if someone switched the
     * config in the appropriate dropdown list
     */
    protected function redirectOnConfigSwitch()
    {
        $request = $this->getRequest();
        if ($request->isPost()) {
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
