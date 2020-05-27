<?php

namespace Icinga\Module\Businessprocess\Renderer\TileRenderer;

use Icinga\Date\DateFormatter;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\HostNode;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\MonitoredNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\ServiceNode;
use Icinga\Module\Businessprocess\Web\Component\StateBall;
use Icinga\Web\Url;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class NodeTile extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $renderer;

    protected $name;

    protected $node;

    protected $path;

    /**
     * @var BaseHtmlElement
     */
    private $actions;

    /**
     * NodeTile constructor.
     * @param Renderer $renderer
     * @param $name
     * @param Node $node
     * @param null $path
     */
    public function __construct(Renderer $renderer, Node $node, $path = null)
    {
        $this->renderer = $renderer;
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
        $this->actions = Html::tag(
            'div',
            [
                'class' => 'actions'
            ]
        );

        return $this->add($this->actions);
    }

    public function render()
    {
        $renderer = $this->renderer;
        $node = $this->node;

        $attributes = $this->getAttributes();
        $attributes->add('class', $renderer->getNodeClasses($node));
        $attributes->add('id', $renderer->getId($node, $this->path));
        if (! $renderer->isLocked()) {
            $attributes->add('data-node-name', $node->getName());
        }

        if (! $renderer->isBreadcrumb()) {
            $this->addDetailsActions();

            if (! $renderer->isLocked()) {
                $this->addActionLinks();
            }
        }
        if (! $node instanceof ImportedNode || $node->getBpConfig()->hasNode($node->getName())) {
            $link = $this->getMainNodeLink();
            if ($renderer->isBreadcrumb()) {
                $state = strtolower($node->getStateName());
                if ($node->isHandled()) {
                    $state = $state . '-handled';
                }
                $link->prepend((new StateBall($state))->addAttributes([
                    'title' => sprintf(
                        '%s %s',
                        $state,
                        DateFormatter::timeSince($node->getLastStateChange())
                    )
                ]));
            }

            $this->add($link);
        } else {
            $this->add(Html::tag(
                'a',
                Html::tag(
                    'span',
                    ['style' => 'font-size: 75%'],
                    sprintf('Trying to access a missing business process node "%s"', $node->getNodeName())
                )
            ));
        }

        if ($node instanceof BpNode && !$renderer->isBreadcrumb()) {
            $this->add(Html::tag(
                'p',
                ['class' => 'children-count'],
                $node->hasChildren()
                    ? Html::tag(
                        'span',
                        null,
                        sprintf('%u %s', $node->countChildren(), mt('businessprocess', 'Children'))
                    )
                    : null
            ));
            $this->add($renderer->renderStateBadges($node->getStateSummary()));
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
        $url = $this->renderer->getBaseUrl();

        $p = $url->getParams();
        if ($node instanceof ImportedNode
            && $this->renderer->getBusinessProcess()->getName() === $node->getBpConfig()->getName()
        ) {
            $p->set('node', $node->getNodeName());
        } elseif ($this->renderer->rendersImportedNode()) {
            $p->set('node', $node->getIdentifier());
        } else {
            $p->set('node', $node->getName());
        }

        if (! empty($this->path)) {
            $p->addValues('path', $this->path);
        }

        return $url;
    }

    protected function makeBpUrl(BpNode $node)
    {
        return $this->buildBaseNodeUrl($node);
    }

    /**
     * @return BaseHtmlElement
     */
    protected function getMainNodeLink()
    {
        $node = $this->node;
        $url = $this->getMainNodeUrl($node);
        if ($node instanceof MonitoredNode) {
            $link = Html::tag('a', ['href' => $url, 'data-base-target' => '_next'], $node->getAlias());
        } else {
            $link = Html::tag('a', ['href' => $url], $node->getAlias());
        }

        return $link;
    }

    protected function addDetailsActions()
    {
        $node = $this->node;
        $url = $this->getMainNodeUrl($node);

        if ($node instanceof BpNode) {
            $this->actions()->add(Html::tag(
                'a',
                [
                    'href'  => $url->with('mode', 'tile'),
                    'title' => mt('businessprocess', 'Show tiles for this subtree')
                ],
                Html::tag('i', ['class' => 'icon icon-dashboard'])
            ))->add(Html::tag(
                'a',
                [
                    'href'  => $url->with('mode', 'tree'),
                    'title' => mt('businessprocess', 'Show this subtree as a tree')
                ],
                Html::tag('i', ['class' => 'icon icon-sitemap'])
            ));
            if ($node instanceof ImportedNode) {
                if ($node->getBpConfig()->hasNode($node->getName())) {
                    $this->actions()->add(Html::tag(
                        'a',
                        [
                            'data-base-target'  => '_next',
                            'href'              => $this->renderer->getSourceUrl($node)->getAbsoluteUrl(),
                            'title'             => mt(
                                'businessprocess',
                                'Show this process as part of its original configuration'
                            )
                        ],
                        Html::tag('i', ['class' => 'icon icon-forward'])
                    ));
                }
            }

            $url = $node->getInfoUrl();

            if ($url !== null) {
                $link = Html::tag(
                    'a',
                    [
                        'href'  => $url,
                        'class' => 'node-info',
                        'title' => sprintf('%s: %s', mt('businessprocess', 'More information'), $url)
                    ],
                    Html::tag('i', ['class' => 'icon icon-info-circled'])
                );
                if (preg_match('#^http(?:s)?://#', $url)) {
                    $link->addAttributes(['target' => '_blank']);
                }
                $this->actions()->add($link);
            }
        } else {
            // $url = $this->makeMonitoredNodeUrl($node);
            if ($node instanceof ServiceNode) {
                $this->actions()->add(Html::tag(
                    'a',
                    ['href' => $node->getUrl(), 'data-base-target' => '_next'],
                    Html::tag('i', ['class' => 'icon icon-service'])
                ));
            } elseif ($node instanceof HostNode) {
                $this->actions()->add(Html::tag(
                    'a',
                    ['href' => $node->getUrl(), 'data-base-target' => '_next'],
                    Html::tag('i', ['class' => 'icon icon-host'])
                ));
            }
        }
    }

    protected function addActionLinks()
    {
        $parent = $this->renderer->getParentNode();
        if ($parent !== null) {
            $baseUrl = Url::fromPath('businessprocess/process/show', [
                'config'    => $parent->getBpConfig()->getName(),
                'node'      => $parent instanceof ImportedNode
                    ? $parent->getNodeName()
                    : $parent->getName(),
                'unlocked'  => true
            ]);
        } else {
            $baseUrl = Url::fromPath('businessprocess/process/show', [
                'config'    => $this->node->getBpConfig()->getName(),
                'unlocked'  => true
            ]);
        }

        if ($this->node instanceof MonitoredNode) {
            $this->actions()->add(Html::tag(
                'a',
                [
                    'href'  => $baseUrl
                        ->with('action', 'simulation')
                        ->with('simulationnode', $this->node->getName()),
                    'title' => mt(
                        'businessprocess',
                        'Show the business impact of this node by simulating a specific state'
                    )
                ],
                Html::tag('i', ['class' => 'icon icon-magic'])
            ));

            $this->actions()->add(Html::tag(
                'a',
                [
                    'href'  => $baseUrl
                        ->with('action', 'editmonitored')
                        ->with('editmonitorednode', $this->node->getName()),
                    'title' => mt('businessprocess', 'Modify this monitored node')
                ],
                Html::tag('i', ['class' => 'icon icon-edit'])
            ));
        }

        if ($this->renderer->getBusinessProcess()->getMetadata()->canModify()
            && $this->node->getBpConfig()->getName() === $this->renderer->getBusinessProcess()->getName()
            && $this->node->getName() !== '__unbound__'
        ) {
            if ($this->node instanceof BpNode) {
                $this->actions()->add(Html::tag(
                    'a',
                    [
                        'href'  => $baseUrl
                            ->with('action', 'edit')
                            ->with('editnode', $this->node->getName()),
                        'title' => mt('businessprocess', 'Modify this business process node')
                    ],
                    Html::tag('i', ['class' => 'icon icon-edit'])
                ));

                $addUrl = $baseUrl->with([
                    'node'      => $this->node->getName(),
                    'action'    => 'add'
                ]);
                $addUrl->getParams()->addValues('path', $this->path);
                $this->actions()->add(Html::tag(
                    'a',
                    [
                        'href'  => $addUrl,
                        'title' => mt('businessprocess', 'Add a new sub-node to this business process')
                    ],
                    Html::tag('i', ['class' => 'icon icon-plus'])
                ));
            }
        }

        if ($this->renderer->getBusinessProcess()->getMetadata()->canModify()) {
            $params = array(
                'action'     => 'delete',
                'deletenode' => $this->node->getName(),
            );

            $this->actions()->add(Html::tag(
                'a',
                [
                    'href'  => $baseUrl->with($params),
                    'title' => mt('businessprocess', 'Delete this node')
                ],
                Html::tag('i', ['class' => 'icon icon-cancel'])
            ));
        }
    }
}
