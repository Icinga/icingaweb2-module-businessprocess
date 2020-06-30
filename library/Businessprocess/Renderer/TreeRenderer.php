<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Date\DateFormatter;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Component\StateBall;
use Icinga\Module\Businessprocess\Web\Form\CsrfToken;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class TreeRenderer extends Renderer
{
    /**
     * @inheritdoc
     */
    public function render()
    {
        $bp = $this->config;
        $htmlId = $bp->getHtmlId();
        $tree = Html::tag(
            'ul',
            [
                'id'                            => $htmlId,
                'class'                         => ['bp', 'sortable', $this->wantsRootNodes() ? '' : 'process'],
                'data-sortable-disabled'        => $this->isLocked() ? 'true' : 'false',
                'data-sortable-data-id-attr'    => 'id',
                'data-sortable-direction'       => 'vertical',
                'data-sortable-group'           => json_encode([
                    'name'  => $this->wantsRootNodes() ? 'root' : $htmlId,
                    'put'   => 'function:rowPutAllowed'
                ]),
                'data-sortable-invert-swap'     => 'true',
                'data-is-root-config'           => $this->wantsRootNodes() ? 'true' : 'false',
                'data-csrf-token'               => CsrfToken::generate()
            ],
            $this->renderBp($bp)
        );
        if ($this->wantsRootNodes()) {
            $tree->getAttributes()->add(
                'data-action-url',
                $this->getUrl()->with(['config' => $bp->getName()])->getAbsoluteUrl()
            );
        } else {
            $nodeName = $this->parent instanceof ImportedNode
                ? $this->parent->getNodeName()
                : $this->parent->getName();
            $tree->getAttributes()
                ->add('data-node-name', $nodeName)
                ->add('data-action-url', $this->getUrl()
                    ->with([
                        'config'    => $this->parent->getBpConfig()->getName(),
                        'node'      => $nodeName
                    ])
                    ->getAbsoluteUrl());
        }

        $this->add($tree);
        return parent::render();
    }

    /**
     * @param BpConfig $bp
     * @return string
     */
    public function renderBp(BpConfig $bp)
    {
        $html = array();
        if ($this->wantsRootNodes()) {
            $nodes = $bp->getChildren();
        } else {
            $nodes = $this->parent->getChildren();
        }

        foreach ($nodes as $name => $node) {
            if ($node instanceof BpNode) {
                $html[] = $this->renderNode($bp, $node);
            } else {
                $html[] = $this->renderChild($bp, $this->parent, $node);
            }
        }

        return $html;
    }

    protected function getStateClassNames(Node $node)
    {
        $state = strtolower($node->getStateName());

        if ($node->isMissing()) {
            return array('missing');
        } elseif ($state === 'ok') {
            if ($node->hasMissingChildren()) {
                return array('ok', 'missing-children');
            } else {
                return array('ok');
            }
        } else {
            return array('problem', $state);
        }
    }

    /**
     * @param Node $node
     * @param array $path
     * @param BpNode $parent
     * @return BaseHtmlElement[]
     */
    public function getNodeIcons(Node $node, array $path = null, BpNode $parent = null)
    {
        $icons = [];
        if (empty($path) && $node instanceof BpNode) {
            $icons[] = Html::tag('i', ['class' => 'icon icon-sitemap']);
        } else {
            $icons[] = $node->getIcon();
        }
        $state = strtolower($node->getStateName($parent !== null ? $parent->getChildState($node) : null));
        if ($node->isHandled()) {
            $state = $state . '-handled';
        }
        $icons[] = (new StateBall($state))->addAttributes([
            'title' => sprintf(
                '%s %s',
                $state,
                DateFormatter::timeSince($node->getLastStateChange())
            )
        ]);
        if ($node->isInDowntime()) {
            $icons[] = Html::tag('i', ['class' => 'icon icon-moon']);
        }
        if ($node->isAcknowledged()) {
            $icons[] = Html::tag('i', ['class' => 'icon icon-ok']);
        }
        return $icons;
    }

    public function getOverriddenState($fakeState, Node $node)
    {
        $overriddenState = Html::tag('div', ['class' => 'overridden-state']);
        $overriddenState->add((new StateBall(strtolower($node->getStateName())))->addAttributes([
            'title' => sprintf(
                '%s',
                $node->getStateName()
            )
        ]));
        $overriddenState->add(Html::tag('i', ['class' => 'icon icon-right-small']));
        $overriddenState->add((new StateBall(strtolower($node->getStateName($fakeState))))->addAttributes([
            'title' => sprintf(
                '%s',
                $node->getStateName($fakeState)
            ),
            'class' => 'last'
        ]));

        return $overriddenState;
    }

    /**
     * @param BpConfig $bp
     * @param Node $node
     * @param array $path
     *
     * @return string
     */
    public function renderNode(BpConfig $bp, Node $node, $path = array())
    {
        $htmlId = $this->getId($node, $path);
        $li = Html::tag(
            'li',
            [
                'id'                => $htmlId,
                'class'             => ['bp', 'movable', $node->getObjectClassName()],
                'data-node-name'    => $node instanceof ImportedNode
                    ? $node->getNodeName()
                    : $node->getName()
            ]
        );
        $attributes = $li->getAttributes();
        $attributes->add('class', $this->getStateClassNames($node));
        if ($node->isHandled()) {
            $attributes->add('class', 'handled');
        }
        if ($node instanceof BpNode) {
            $attributes->add('class', 'operator');
        } else {
            $attributes->add('class', 'node');
        }

        $div = Html::tag('div');
        $li->add($div);

        $div->add($node->getLink());
        $div->add($this->getNodeIcons($node, $path));

        $div->add(Html::tag('span', null, $node->getAlias()));

        if ($node instanceof BpNode) {
            $div->add(Html::tag('span', ['class' => 'op'], $node->operatorHtml()));
        }

        if ($node instanceof BpNode && $node->hasInfoUrl()) {
            $div->add($this->createInfoAction($node));
        }

        $differentConfig = $node->getBpConfig()->getName() !== $this->getBusinessProcess()->getName();
        if (! $this->isLocked() && !$differentConfig) {
            $div->add($this->getActionIcons($bp, $node));
        } elseif ($differentConfig) {
            $div->add($this->actionIcon(
                'forward',
                $this->getSourceUrl($node)->addParams(['mode' => 'tree'])->getAbsoluteUrl(),
                mt('businessprocess', 'Show this process as part of its original configuration')
            )->addAttributes(['data-base-target' => '_next']));
        }

        $ul = Html::tag('ul', [
            'class'                         => ['bp', 'sortable'],
            'data-sortable-disabled'        => ($this->isLocked() || $differentConfig) ? 'true' : 'false',
            'data-sortable-invert-swap'     => 'true',
            'data-sortable-data-id-attr'    => 'id',
            'data-sortable-draggable'       => '.movable',
            'data-sortable-direction'       => 'vertical',
            'data-sortable-group'           => json_encode([
                'name'  => $htmlId, // Unique, so that the function below is the only deciding factor
                'put'   => 'function:rowPutAllowed'
            ]),
            'data-csrf-token'               => CsrfToken::generate(),
            'data-action-url'               => $this->getUrl()
                ->with([
                    'config'    => $node->getBpConfig()->getName(),
                    'node'      => $node instanceof ImportedNode
                        ? $node->getNodeName()
                        : $node->getName()
                ])
                ->getAbsoluteUrl()
        ]);
        $li->add($ul);

        $path[] = $differentConfig ? $node->getIdentifier() : $node->getName();
        foreach ($node->getChildren() as $name => $child) {
            if ($child instanceof BpNode) {
                $ul->add($this->renderNode($bp, $child, $path));
            } else {
                $ul->add($this->renderChild($bp, $node, $child, $path));
            }
        }

        return $li;
    }

    protected function renderChild($bp, BpNode $parent, Node $node, $path = null)
    {
        $li = Html::tag('li', [
            'class'             => 'movable',
            'id'                => $this->getId($node, $path ?: []),
            'data-node-name'    => $node->getName()
        ]);

        $li->add($this->getNodeIcons($node, $path, $parent));

        $link = $node->getLink();
        $link->getAttributes()->set('data-base-target', '_next');
        $li->add($link);

        if (($overriddenState = $parent->getChildState($node)) !== $node->getState()) {
            $li->add($this->getOverriddenState($overriddenState, $node));
        }

        if (! $this->isLocked() && $node->getBpConfig()->getName() === $this->getBusinessProcess()->getName()) {
            $li->add($this->getActionIcons($bp, $node));
        }

        return $li;
    }

    protected function getActionIcons(BpConfig $bp, Node $node)
    {
        if ($node instanceof BpNode) {
            if ($bp->getMetadata()->canModify()) {
                return [$this->createEditAction($bp, $node), $this->renderAddNewNode($node)];
            } else {
                return '';
            }
        } else {
            return $this->createSimulationAction($bp, $node);
        }
    }

    protected function createEditAction(BpConfig $bp, BpNode $node)
    {
        return $this->actionIcon(
            'edit',
            $this->getUrl()->with(array(
                'action'   => 'edit',
                'editnode' => $node->getName()
            )),
            mt('businessprocess', 'Modify this node')
        );
    }

    protected function createSimulationAction(BpConfig $bp, Node $node)
    {
        return $this->actionIcon(
            'magic',
            $this->getUrl()->with(array(
                //'config' => $bp->getName(),
                'action' => 'simulation',
                'simulationnode' => $node->getName()
            )),
            mt('businessprocess', 'Simulate a specific state')
        );
    }

    protected function createInfoAction(BpNode $node)
    {
        $url = $node->getInfoUrl();
        return $this->actionIcon(
            'help',
            $url,
            sprintf('%s: %s', mt('businessprocess', 'More information'), $url)
        )->addAttributes(['target' => '_blank']);
    }

    protected function actionIcon($icon, $url, $title)
    {
        return Html::tag(
            'a',
            [
                'href'  => $url,
                'title' => $title,
                'class' => 'action-link'
            ],
            Html::tag('i', ['class' => 'icon icon-' . $icon])
        );
    }

    protected function renderAddNewNode($parent)
    {
        return $this->actionIcon(
            'plus',
            $this->getUrl()
                ->with('action', 'add')
                ->with('node', $parent->getName()),
            mt('businessprocess', 'Add a new business process node')
        );
    }
}
