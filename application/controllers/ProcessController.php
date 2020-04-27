<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Date\DateFormatter;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Renderer\Breadcrumb;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\Renderer\TileRenderer;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\ConfigDiff;
use Icinga\Module\Businessprocess\Storage\LegacyConfigRenderer;
use Icinga\Module\Businessprocess\Web\Component\ActionBar;
use Icinga\Module\Businessprocess\Web\Component\RenderedProcessActionBar;
use Icinga\Module\Businessprocess\Web\Component\Tabs;
use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Util\Json;
use Icinga\Web\Notification;
use Icinga\Web\Url;
use Icinga\Web\Widget\Tabextension\DashboardAction;
use Icinga\Web\Widget\Tabextension\OutputFormat;
use ipl\Html\Html;
use ipl\Html\HtmlString;

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
            ->add(Html::tag('h1', null, $title));

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
            ->add(Html::tag('h1', null, $title));

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

        if ($bp->getBackendName() === '_icingadb') {
            IcingaDbState::apply($bp);
        }
        else {
            MonitoringState::apply($bp);
        }

        $this->handleSimulations($bp);

        $this->setTitle($this->translate('Business Process "%s"'), $bp->getTitle());


        $renderer = $this->prepareRenderer($bp, $node);

        if (! $this->showFullscreen && ($node === null || ! $renderer->rendersImportedNode())) {
            if ($this->params->get('unlocked')) {
                $renderer->unlock();
            }

            if ($bp->isEmpty() && $renderer->isLocked()) {
                $this->redirectNow($this->url()->with('unlocked', true));
            }
        }

        $this->handleFormatRequest($bp, $node);

        $this->prepareControls($bp, $renderer);

        $this->tabs()->extend(new OutputFormat());

        $missing = $bp->getMissingChildren();
        if (! empty($missing)) {
            if (($count = count($missing)) > 10) {
                $missing = array_slice($missing, 0, 10);
                $missing[] = '...';
            }
            $bp->addError('There are %d missing nodes: %s', $count, implode(', ', $missing));
        }
        $this->content()->add($this->showHints($bp));
        $this->content()->add($this->showWarnings($bp));
        $this->content()->add($this->showErrors($bp));
        $this->content()->add($renderer);
        $this->loadActionForm($bp, $node);
        $this->setDynamicAutorefresh();
    }

    protected function prepareControls($bp, $renderer)
    {
        $controls = $this->controls();

        if ($this->showFullscreen) {
            $controls->getAttributes()->add('class', 'want-fullscreen');
            $controls->add(Html::tag(
                'a',
                [
                    'href'  => $this->url()->without('showFullscreen')->without('view'),
                    'title' => $this->translate('Leave full screen and switch back to normal mode'),
                    'style' => 'float: right'
                ],
                Html::tag('i', ['class' => 'icon icon-resize-small'])
            ));
        }

        if (! ($this->showFullscreen || $this->view->compact)) {
            $controls->add($this->getProcessTabs($bp, $renderer));
            $controls->getAttributes()->add('class', 'separated');
        }

        $controls->add(Breadcrumb::create(clone $renderer));
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
                ->setSuccessUrl(Url::fromRequest()->without('action'))
                ->setStorage($this->storage())
                ->setProcess($bp)
                ->setParentNode($node)
                ->setSession($this->session())
                ->handleRequest();
        } elseif ($action === 'editmonitored' && $canEdit) {
            $form = $this->loadForm('EditNode')
                ->setSuccessUrl(Url::fromRequest()->without('action'))
                ->setProcess($bp)
                ->setNode($bp->getNode($this->params->get('editmonitorednode')))
                ->setParentNode($node)
                ->setSession($this->session())
                ->handleRequest();
        } elseif ($action === 'delete' && $canEdit) {
            $form = $this->loadForm('DeleteNode')
                ->setSuccessUrl(Url::fromRequest()->without('action'))
                ->setProcess($bp)
                ->setNode($bp->getNode($this->params->get('deletenode')))
                ->setParentNode($node)
                ->setSession($this->session())
                ->handleRequest();
        } elseif ($action === 'edit' && $canEdit) {
            $form = $this->loadForm('Process')
                ->setSuccessUrl(Url::fromRequest()->without('action'))
                ->setProcess($bp)
                ->setNode($bp->getNode($this->params->get('editnode')))
                ->setSession($this->session())
                ->handleRequest();
        } elseif ($action === 'simulation') {
            $form = $this->loadForm('simulation')
                ->setSuccessUrl(Url::fromRequest()->without('action'))
                ->setNode($bp->getNode($this->params->get('simulationnode')))
                ->setSimulation(Simulation::fromSession($this->session()))
                ->handleRequest();
        } elseif ($action === 'move') {
            $form = $this->loadForm('MoveNode')
                ->setProcess($bp)
                ->setParentNode($node)
                ->setSession($this->session())
                ->setNode($bp->getNode($this->params->get('movenode')))
                ->handleRequest();
        }

        if ($form) {
            $this->content()->prepend(HtmlString::create((string) $form));
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
            $ul = Html::tag('ul', array('class' => 'warning'));
            foreach ($bp->getWarnings() as $warning) {
                $ul->add(Html::tag('li')->setContent($warning));
            }

            return $ul;
        } else {
            return null;
        }
    }

    protected function showErrors(BpConfig $bp)
    {
        if ($bp->hasWarnings()) {
            $ul = Html::tag('ul', array('class' => 'error'));
            foreach ($bp->getErrors() as $msg) {
                $ul->add(Html::tag('li')->setContent($msg));
            }

            return $ul;
        } else {
            return null;
        }
    }

    protected function showHints(BpConfig $bp)
    {
        $ul = Html::tag('ul', ['class' => 'error']);
        foreach ($bp->getErrors() as $error) {
            $ul->add(Html::tag('li')->setContent($error));
        }
        if ($bp->hasChanges()) {
            $li = Html::tag('li')->setSeparator(' ');
            $li->add(sprintf(
                $this->translate('This process has %d pending change(s).'),
                $bp->countChanges()
            ))->add(Html::tag(
                'a',
                [
                    'href' => Url::fromPath('businessprocess/process/config')
                        ->setParams($this->getRequest()->getUrl()->getParams())
                ],
                $this->translate('Store')
            ))->add(Html::tag(
                'a',
                ['href' => $this->url()->with('dismissChanges', true)],
                $this->translate('Dismiss')
            ));
            $ul->add($li);
        }

        if ($bp->hasSimulations()) {
            $li = Html::tag('li')->setSeparator(' ');
            $li->add(sprintf(
                $this->translate('This process shows %d simulated state(s).'),
                $bp->countSimulations()
            ))->add(Html::tag(
                'a',
                ['href' => $this->url()->with('dismissSimulations', true)],
                $this->translate('Dismiss')
            ));
            $ul->add($li);
        }

        if (! $ul->isEmpty()) {
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
            ->add(Html::tag('h1', null, $title))
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
            ->add(Html::tag('h1', null, $title))
            ->add($this->createConfigActionBar($bp));

        $url = Url::fromPath('businessprocess/process/show')
            ->setParams($this->getRequest()->getUrl()->getParams());
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
            $actionBar->add(Html::tag(
                'a',
                [
                    'href'  => Url::fromPath('businessprocess/process/source', $params),
                    'class' => 'icon-doc-text',
                    'title' => $this->translate('Show source code')
                ],
                $this->translate('Source')
            ));
        } else {
            $params = array(
                'config'   => $config->getName(),
                'showDiff' => true
            );

            $actionBar->add(Html::tag(
                'a',
                [
                    'href'  => Url::fromPath('businessprocess/process/source', $params),
                    'class' => 'icon-flapping',
                    'title' => $this->translate('Highlight changes')
                ],
                $this->translate('Diff')
            ));
        }

        $actionBar->add(Html::tag(
            'a',
            [
                'href'      => Url::fromPath('businessprocess/process/download', ['config' => $config->getName()]),
                'class'     => 'icon-download',
                'target'    => '_blank',
                'title'     => $this->translate('Download process configuration')
            ],
            $this->translate('Download')
        ));

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

    protected function handleFormatRequest(BpConfig $bp, BpNode $node = null)
    {
        $desiredContentType = $this->getRequest()->getHeader('Accept');
        if ($desiredContentType === 'application/json') {
            $desiredFormat = 'json';
        } elseif ($desiredContentType === 'text/csv') {
            $desiredFormat = 'csv';
        } else {
            $desiredFormat = strtolower($this->params->get('format', 'html'));
        }

        switch ($desiredFormat) {
            case 'json':
                $response = $this->getResponse();
                $response
                    ->setHeader('Content-Type', 'application/json')
                    ->setHeader('Cache-Control', 'no-store')
                    ->setHeader(
                        'Content-Disposition',
                        'inline; filename=' . $this->getRequest()->getActionName() . '.json'
                    )
                    ->appendBody(Json::sanitize($node !== null ? $node->toArray() : $bp->toArray()))
                    ->sendResponse();
                exit;
            case 'csv':
                $csv = fopen('php://temp', 'w');

                fputcsv($csv, ['Path', 'Name', 'State', 'Since', 'In_Downtime']);

                foreach ($node !== null ? $node->toArray(null, true) : $bp->toArray(true) as $node) {
                    $data = [$node['path'], $node['name']];

                    if (isset($node['state'])) {
                        $data[] = $node['state'];
                    }

                    if (isset($node['since'])) {
                        $data[] = DateFormatter::formatDateTime($node['since']);
                    }
                    
                    if (isset($node['in_downtime'])) {
                        $data[] = $node['in_downtime'];
                    }

                    fputcsv($csv, $data);
                }

                $response = $this->getResponse();
                $response
                    ->setHeader('Content-Type', 'text/csv')
                    ->setHeader('Cache-Control', 'no-store')
                    ->setHeader(
                        'Content-Disposition',
                        'attachment; filename=' . $this->getRequest()->getActionName() . '.csv'
                    )
                    ->sendHeaders();

                rewind($csv);

                fpassthru($csv);

                exit;
        }
    }
}
