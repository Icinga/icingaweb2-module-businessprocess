<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\ConfigDiff;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\HtmlString;
use Icinga\Module\Businessprocess\Html\HtmlTag;
use Icinga\Module\Businessprocess\Html\Icon;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Renderer\Breadcrumb;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\Renderer\TileRenderer;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\Storage\LegacyConfigRenderer;
use Icinga\Module\Businessprocess\Web\Component\ActionBar;
use Icinga\Module\Businessprocess\Web\Component\RenderedProcessActionBar;
use Icinga\Module\Businessprocess\Web\Component\Tabs;
use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Businessprocess\Web\Url;
use Icinga\Web\Notification;
use Icinga\Web\Widget\Tabextension\DashboardAction;

class ProcessController extends Controller
{
    /** @var Renderer */
    protected $renderer;

    /**
     * Create a new Business Process Configuration
     */
    public function createAction()
    {
        $this->assertPermission('businessprocess/create');

        $title = $this->translate('Create a new Business Process');
        $this->setTitle($title);
        $this->controls()
            ->add($this->tabsForCreate()->activate('create'))
            ->add(HtmlTag::h1($title));

        $this->content()->add(
            $this->loadForm('bpConfig')
            ->setStorage($this->storage())
            ->setSuccessUrl('businessprocess/process/show')
            ->handleRequest()
        );
    }

    /**
     * Upload an existing Business Process Configuration
     */
    public function uploadAction()
    {
        $this->assertPermission('businessprocess/create');

        $title = $this->translate('Upload a Business Process Config file');
        $this->setTitle($title);
        $this->controls()
            ->add($this->tabsForCreate()->activate('upload'))
            ->add(HtmlTag::h1($title));

        $this->content()->add(
            $this->loadForm('BpUpload')
                ->setStorage($this->storage())
                ->setSuccessUrl('businessprocess/process/show')
                ->handleRequest()
        );
    }

    /**
     * Show a business process
     */
    public function showAction()
    {
        $bp = $this->loadModifiedBpConfig();
        $node = $this->getNode($bp);

        MonitoringState::apply($bp);
        $this->handleSimulations($bp);

        $this->setTitle($this->translate('Business Process "%s"'), $bp->getTitle());

        $renderer = $this->prepareRenderer($bp, $node);

        if ($this->params->get('unlocked')) {
            $renderer->unlock();
        }

        if ($bp->isEmpty() && $renderer->isLocked()) {
            $this->redirectNow($this->url()->with('unlocked', true));
        }

        $this->prepareControls($bp, $renderer);
        $missing = $bp->getMissingChildren();
        if (! empty($missing)) {
            if (($count = count($missing)) > 10) {
                $missing = array_slice($missing, 0, 10);
                $missing[] = '...';
            }
            $bp->addError(sprintf('There are %d missing nodes: %s', $count, implode(', ', $missing)));
        }
        $this->content()->addContent($this->showHints($bp));
        $this->content()->addContent($this->showWarnings($bp));
        $this->content()->addContent($this->showErrors($bp));
        $this->content()->add($renderer);
        $this->loadActionForm($bp, $node);
        $this->setDynamicAutorefresh();
    }

    protected function prepareControls($bp, $renderer)
    {
        $controls = $this->controls();

        if ($this->showFullscreen) {
            $controls->attributes()->add('class', 'want-fullscreen');
            $controls->add(
                Link::create(
                    Icon::create('resize-small'),
                    $this->url()->without('showFullscreen')->without('view'),
                    null,
                    array(
                        'style' => 'float: right',
                        'title' => $this->translate(
                            'Leave full screen and switch back to normal mode'
                        )
                    )
                )
            );
        }

        if (! ($this->showFullscreen || $this->view->compact)) {
            $controls->add($this->getProcessTabs($bp, $renderer));
        }
        if (! $this->view->compact) {
            $controls->add(Element::create('h1')->setContent($this->view->title));
        }
        $controls->add(Breadcrumb::create($renderer));
        if (! $this->showFullscreen && ! $this->view->compact) {
            $controls->add(
                new RenderedProcessActionBar($bp, $renderer, $this->Auth(), $this->url())
            );
        }
    }

    protected function getNode(BpConfig $bp)
    {
        if ($nodeName = $this->params->get('node')) {
            return $bp->getNode($nodeName);
        } else {
            return null;
        }
    }

    protected function prepareRenderer($bp, $node)
    {
        if ($this->renderer === null) {
            if ($this->params->get('mode') === 'tree') {
                $renderer = new TreeRenderer($bp, $node);
            } else {
                $renderer = new TileRenderer($bp, $node);
            }
            $renderer->setUrl($this->url())
                ->setPath($this->params->getValues('path'));

            $this->renderer = $renderer;
        }

        return $this->renderer;
    }

    protected function getProcessTabs(BpConfig $bp, Renderer $renderer)
    {
        $tabs = $this->singleTab($bp->getTitle());
        if ($renderer->isLocked()) {
            $tabs->extend(new DashboardAction());
        }

        return $tabs;
    }

    protected function handleSimulations(BpConfig $bp)
    {
        $simulation = Simulation::fromSession($this->session());

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

    protected function loadActionForm(BpConfig $bp, Node $node = null)
    {
        $action = $this->params->get('action');
        $form = null;
        if ($this->showFullscreen) {
            return;
        }

        $canEdit =  $bp->getMetadata()->canModify();

        if ($action === 'add' && $canEdit) {
            $form = $this->loadForm('AddNode')
                ->setProcess($bp)
                ->setParentNode($node)
                ->setSession($this->session())
                ->handleRequest();
        } elseif ($action === 'delete' && $canEdit) {
                $form =$this->loadForm('DeleteNode')
                    ->setProcess($bp)
                    ->setNode($bp->getNode($this->params->get('deletenode')))
                    ->setParentNode($node)
                    ->setSession($this->session())
                    ->handleRequest();
        } elseif ($action === 'edit' && $canEdit) {
            $form =$this->loadForm('Process')
                ->setProcess($bp)
                ->setNode($bp->getNode($this->params->get('editnode')))
                ->setSession($this->session())
                ->handleRequest();
        } elseif ($action === 'simulation') {
            $form = $this->loadForm('simulation')
                ->setNode($bp->getNode($this->params->get('simulationnode')))
                ->setSimulation(Simulation::fromSession($this->session()))
                ->handleRequest();
        }

        if ($form) {
            $this->content()->prependContent(HtmlString::create((string) $form));
        }
    }

    protected function setDynamicAutorefresh()
    {
        if (! $this->isXhr()) {
            // This will trigger the very first XHR refresh immediately on page
            // load. Please not that this may hammer the server in case we would
            // decide to use autorefreshInterval for HTML meta-refreshes also.
            $this->setAutorefreshInterval(1);
            return;
        }

        if ($this->params->get('action')) {
            $this->setAutorefreshInterval(45);
        } else {
            $this->setAutorefreshInterval(10);
        }
    }

    protected function showWarnings(BpConfig $bp)
    {
        if ($bp->hasWarnings()) {
            $ul = Element::create('ul', array('class' => 'warning'));
            foreach ($bp->getWarnings() as $warning) {
                $ul->createElement('li')->addContent($warning);
            }

            return $ul;
        } else {
            return null;
        }
    }

    protected function showErrors(BpConfig $bp)
    {
        if ($bp->hasWarnings()) {
            $ul = Element::create('ul', array('class' => 'error'));
            foreach ($bp->getErrors() as $msg) {
                $ul->createElement('li')->addContent($msg);
            }

            return $ul;
        } else {
            return null;
        }
    }

    protected function showHints(BpConfig $bp)
    {
        $ul = Element::create('ul', array('class' => 'error'));
        foreach ($bp->getErrors() as $error) {
            $ul->createElement('li')->addContent($error);
        }
        if ($bp->hasChanges()) {
            $ul->createElement('li')->setSeparator(' ')->addContent(sprintf(
                $this->translate('This process has %d pending change(s).'),
                $bp->countChanges()
            ))->addContent(
                Link::create(
                    $this->translate('Store'),
                    'businessprocess/process/config',
                    array('config' => $bp->getName())
                )
            )->addContent(
                Link::create(
                    $this->translate('Dismiss'),
                    $this->url()->with('dismissChanges', true),
                    null
                )
            );
        }

        if ($bp->hasSimulations()) {
            $ul->createElement('li')->setSeparator(' ')->addContent(sprintf(
                $this->translate('This process shows %d simulated state(s).'),
                $bp->countSimulations()
            ))->addContent(Link::create(
                $this->translate('Dismiss'),
                $this->url()->with('dismissSimulations', true)
            ));
        }

        if ($ul->hasContent()) {
            return $ul;
        } else {
            return null;
        }
    }

    /**
     * Show the source code for a process
     */
    public function sourceAction()
    {
        $this->assertPermission('businessprocess/modify');

        $bp = $this->loadModifiedBpConfig();
        $this->view->showDiff = $showDiff = (bool) $this->params->get('showDiff', false);

        $this->view->source = LegacyConfigRenderer::renderConfig($bp);
        if ($this->view->showDiff) {
            $this->view->diff = ConfigDiff::create(
                $this->storage()->getSource($this->view->configName),
                $this->view->source
            );
            $title = sprintf(
                $this->translate('%s: Source Code Differences'),
                $bp->getTitle()
            );
        } else {
            $title = sprintf(
                $this->translate('%s: Source Code'),
                $bp->getTitle()
            );
        }

        $this->setTitle($title);
        $this->controls()
            ->add($this->tabsForConfig($bp)->activate('source'))
            ->add(HtmlTag::h1($title))
            ->add($this->createConfigActionBar($bp, $showDiff));

        $this->setViewScript('process/source');
    }

    /**
     * Download a process configuration file
     */
    public function downloadAction()
    {
        $this->assertPermission('businessprocess/modify');

        $config = $this->loadModifiedBpConfig();
        $response = $this->getResponse();
        $response->setHeader(
            'Content-Disposition',
            sprintf(
                'attachment; filename="%s.conf";',
                $config->getName()
            )
        );
        $response->setHeader('Content-Type', 'text/plain');

        echo LegacyConfigRenderer::renderConfig($config);
        $this->doNotRender();
    }

    /**
     * Modify a business process configuration
     */
    public function configAction()
    {
        $this->assertPermission('businessprocess/modify');

        $bp = $this->loadModifiedBpConfig();

        $title = sprintf(
            $this->translate('%s: Configuration'),
            $bp->getTitle()
        );
        $this->setTitle($title);
        $this->controls()
            ->add($this->tabsForConfig($bp)->activate('config'))
            ->add(HtmlTag::h1($title))
            ->add($this->createConfigActionBar($bp));

        $url = Url::fromPath(
            'businessprocess/process/show?unlocked',
            array('config' => $bp->getName())
        );
        $this->content()->add(
            $this->loadForm('bpConfig')
                ->setProcessConfig($bp)
                ->setStorage($this->storage())
                ->setSuccessUrl($url)
                ->handleRequest()
        );
    }

    protected function createConfigActionBar(BpConfig $config, $showDiff = false)
    {
        $actionBar = new ActionBar();

        if ($showDiff) {
            $params = array('config' => $config->getName());
            $actionBar->add(
                Link::create(
                    $this->translate('Source'),
                    'businessprocess/process/source',
                    $params,
                    array(
                        'class' => 'icon-doc-text',
                        'title' => $this->translate('Show source code'),
                    )
                )
            );
        } else {
            $params = array(
                'config'   => $config->getName(),
                'showDiff' => true
            );

            $actionBar->add(
                Link::create(
                    $this->translate('Diff'),
                    'businessprocess/process/source',
                    $params,
                    array(
                        'class' => 'icon-flapping',
                        'title' => $this->translate('Highlight changes'),
                    )
                )
            );
        }

        $actionBar->add(
            Link::create(
                $this->translate('Download'),
                'businessprocess/process/download',
                array('config' => $config->getName()),
                array(
                    'target' => '_blank',
                    'class'  => 'icon-download',
                    'title'  => $this->translate('Download process configuration')
                )
            )
        );

        return $actionBar;
    }

    protected function tabsForShow()
    {
        return $this->tabs()->add('show', array(
            'label' => $this->translate('Business Process'),
            'url'   => $this->url()
        ));
    }

    /**
     * @return Tabs
     */
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

    protected function tabsForConfig(BpConfig $config)
    {
        $params = array(
            'config' => $config->getName()
        );

        $tabs = $this->tabs()->add('config', array(
            'label' => $this->translate('Process Configuration'),
            'url'   =>Url::fromPath('businessprocess/process/config', $params)
        ));

        if ($this->params->get('showDiff')) {
            $params['showDiff'] = true;
        }

        $tabs->add('source', array(
            'label' => $this->translate('Source'),
            'url'   =>Url::fromPath('businessprocess/process/source', $params)
        ));

        return $tabs;
    }
}
