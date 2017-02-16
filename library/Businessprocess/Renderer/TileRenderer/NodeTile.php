<?php

namespace Icinga\Module\Businessprocess\Renderer\TileRenderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\HostNode;
use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\HtmlString;
use Icinga\Module\Businessprocess\Html\Icon;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\MonitoredNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\ServiceNode;

class NodeTile extends BaseElement
{
    protected $tag = 'div';

    protected $renderer;

    protected $name;

    protected $node;

    protected $path;

    /**
     * @var Container
     */
    private $actions;

    /**
     * NodeTile constructor.
     * @param Renderer $renderer
     * @param $name
     * @param Node $node
     * @param null $path
     */
    public function __construct(Renderer $renderer, $name, Node $node, $path = null)
    {
        $this->renderer = $renderer;
        $this->name = $name;
        $this->node = $node;
        $this->path = $path;
    }

    protected function actions()
    {
        if ($this->actions === null) {
            $this->addActions();
        }
        return $this->actions;
    }

    protected function addActions()
    {
        $this->actions = Container::create(
            array(
                'class'            => 'actions',
                'data-base-target' => '_self'
            )
        );

        return $this->add($this->actions);
    }

    public function render()
    {
        $renderer = $this->renderer;
        $node = $this->node;

        $attributes = $this->attributes();
        $attributes->add('class', $renderer->getNodeClasses($node));
        $attributes->add('id', 'bp-' . (string) $node);

        $this->addActions();

        $link = $this->getMainNodeLink();
        $this->add($link);

        if ($node instanceof BpNode) {
            if ($renderer->isBreadcrumb()) {
                $link->addContent($renderer->renderStateBadges($node->getStateSummary()));
            } else {
                $this->addContent($renderer->renderStateBadges($node->getStateSummary()));
            }
        }

        if (! $renderer->isBreadcrumb()) {
            $this->addDetailsActions();
        }

        if (! $renderer->isLocked()) {
            $this->addActionLinks();
        }

        return parent::render();
    }

    protected function getMainNodeUrl(Node $node)
    {
        if ($node instanceof BpNode) {
            return $this->makeBpUrl($node);
        } else {
            /** @var MonitoredNode $node */
            return $node->getUrl();
        }
    }

    protected function buildBaseNodeUrl(Node $node)
    {
        $path = $this->path;
        $name = $this->name; // TODO: ??
        $renderer = $this->renderer;

        $bp = $renderer->getBusinessProcess();
        $params = array(
            'config' => $node instanceof ImportedNode ?
                $node->getConfigName() :
                $bp->getName()
        );

        if ($name !== null) {
            $params['node'] = $name;
        }

        $url = $renderer->getBaseUrl();
        $p = $url->getParams();
        $p->mergeValues($params);
        if (! empty($path)) {
            $p->addValues('path', $path);
        }

        return $url;
    }

    protected function makeBpUrl(BpNode $node)
    {
        return $this->buildBaseNodeUrl($node);
    }

    protected function makeMonitoredNodeUrl(MonitoredNode $node)
    {
        $path = $this->path;
        $name = $this->name; // TODO: ??
        $renderer = $this->renderer;

        $bp = $renderer->getBusinessProcess();
        $params = array(
            'config' => $bp->getName()
        );

        if ($name !== null) {
            $params['node'] = $node->getName();
        }

        $url = $renderer->getBaseUrl();
        $p = $url->getParams();
        $p->mergeValues($params);
        if (! empty($path)) {
            $p->addValues('path', $path);
        }

        return $url;
    }

    /**
     * @return Link
     */
    protected function getMainNodeLink()
    {
        $node = $this->node;
        $url = $this->getMainNodeUrl($node);
        if ($node instanceof ServiceNode) {
            $link = Link::create(
                $node->getAlias(),
                $url,
                null,
                array('data-base-target' => '_next')
            );
        } elseif ($node instanceof HostNode) {
            $link = Link::create(
                $node->getHostname(),
                $url,
                null,
                array('data-base-target' => '_next')
            );
        } else {
            $link = Link::create($node->getAlias(), $url);
            if ($node instanceof ImportedNode) {
                $link->attributes()->add('data-base-target', '_next');
            }
        }

        return $link;
    }

    protected function addDetailsActions()
    {
        $node = $this->node;
        $url = $this->getMainNodeUrl($node);

        if ($node instanceof BpNode) {
            $this->actions()->add(Link::create(
                Icon::create('dashboard'),
                $url->with('mode', 'tile'),
                null,
                array(
                    'title' => $this->translate('Show tiles for this subtree'),
                    'data-base-target' => '_next'
                )
            ))->add(Link::create(
                Icon::create('sitemap'),
                $url->with('mode', 'tree'),
                null,
                array(
                    'title' => $this->translate('Show this subtree as a tree'),
                    'data-base-target' => '_next'
                )
            ));
        } else {
            // $url = $this->makeMonitoredNodeUrl($node);
            if ($node instanceof ServiceNode) {
                $this->actions()->add(Link::create(
                    Icon::create('service'),
                    $node->getUrl(),
                    null,
                    array('data-base-target' => '_next')
                ));
            } elseif ($node instanceof HostNode) {
                $this->actions()->add(Link::create(
                    Icon::create('host'),
                    $node->getUrl(),
                    null,
                    array('data-base-target' => '_next')
                ));
            }
        }
    }

    protected function addActionLinks()
    {
        $node = $this->node;
        $renderer = $this->renderer;
        if ($node instanceof MonitoredNode) {
            $this->actions()->add(Link::create(
                Icon::create('magic'),
                $renderer->getUrl()->with('action', 'simulation')
                    ->with('simulationnode', $this->name),
                null,
                array('title' => $this->translate(
                    'Show the business impact of this node by simulating a specific state'
                ))
            ));
        }

        if (! $this->renderer->getBusinessProcess()->getMetadata()->canModify()) {
            return;
        }

        if ($node instanceof BpNode) {
            $this->actions()->add(Link::create(
                Icon::create('edit'),
                $renderer->getUrl()->with('action', 'edit')->with('editnode', $node->getName()),
                null,
                array('title' => $this->translate('Modify this business process node'))
            ));
        }

        $params = array(
            'action'     => 'delete',
            'deletenode' => $node->getName(),
        );

        $this->actions()->add(Link::create(
            Icon::create('cancel'),
            $renderer->getUrl()->with($params),
            null,
            array('title' => $this->translate('Delete this node'))
        ));
    }
}
