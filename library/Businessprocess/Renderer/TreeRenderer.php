<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Node;
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
        $this->add(Html::tag(
            'ul',
            [
                'id'                            => $bp->getHtmlId(),
                'class'                         => ['tree', 'sortable'],
                'data-sortable-disabled'        => $this->isLocked(),
                'data-sortable-data-id-attr'    => 'id',
                'data-sortable-direction'       => 'vertical',
                'data-sortable-group'           => json_encode([
                    'name'  => 'root',
                    'put'   => 'function:rowPutAllowed'
                ]),
                'data-sortable-invert-swap'     => 'true',
                'data-csrf-token'               => CsrfToken::generate(),
                'data-action-url'               => $this->getUrl()->getAbsoluteUrl()
            ],
            $this->renderBp($bp)
        ));

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

        $html[] = Html::tag('li', ['class' => 'placeholder']);
        foreach ($nodes as $name => $node) {
            $html[] = $this->renderNode($bp, $node);
        }

        return $html;
    }

    /**
     * @param Node $node
     * @param $path
     * @return string
     */
    protected function getId(Node $node, $path)
    {
        return md5(implode(';', $path) . (string) $node);
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
     * @return BaseHtmlElement[]
     */
    public function getNodeIcons(Node $node)
    {
        $icons = array();
        if ($node->isInDowntime()) {
            $icons[] = Html::tag('i', ['class' => 'icon icon-moon']);
        }
        if ($node->isAcknowledged()) {
            $icons[] = Html::tag('i', ['class' => 'icon icon-ok']);
        }
        return $icons;
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
        $table = Html::tag(
            'li',
            [
                'id'                => $this->getId($node, $path),
                'class'             => ['bp', 'movable', $node->getObjectClassName()],
                'data-node-name'    => $node->getName()
            ]
        );
        $attributes = $table->getAttributes();
        $attributes->add('class', $this->getStateClassNames($node));
        if ($node->isHandled()) {
            $attributes->add('class', 'handled');
        }
        if ($node instanceof BpNode) {
            $attributes->add('class', 'operator');
        } else {
            $attributes->add('class', 'node');
        }

        if ($node instanceof BpNode) {
            $table->add(Html::tag('span', ['class' => 'op'], $node->operatorHtml()));
        }

        $td = Html::tag('div');
        $table->add($td);

        if ($node instanceof BpNode && $node->hasInfoUrl()) {
            $td->add($this->createInfoAction($node));
        }

        if (! $this->isLocked()) {
            $td->add($this->getActionIcons($bp, $node));
        }

        $link = $node->getLink();
        $link->getAttributes()->set('data-base-target', '_next');
        $link->add($this->getNodeIcons($node));

        if ($node->hasChildren()) {
            $link->add($this->renderStateBadges($node->getStateSummary()));
        }

        if ($time = $node->getLastStateChange()) {
            $since = $this->timeSince($time)->prepend(
                sprintf(' (%s ', $node->getStateName())
            )->add(')');
            $link->add($since);
        }

        $td->add($link);

        $tbody = Html::tag('ul', [
            'class'                         => 'sortable',
            'data-sortable-disabled'        => $this->isLocked(),
            'data-sortable-data-id-attr'    => 'id',
            'data-sortable-draggable'       => '.movable',
            'data-sortable-direction'       => 'vertical',
            'data-sortable-group'           => json_encode([
                'name'  => 'branch',
                'put'   => 'function:rowPutAllowed'
            ]),
            'data-csrf-token'               => CsrfToken::generate(),
            'data-action-url'               => $this->getUrl()
                ->overwriteParams(['node' => (string) $node])
                ->getAbsoluteUrl()
        ]);
        $table->add($tbody);

        $path[] = (string) $node;
        foreach ($node->getChildren() as $name => $child) {
            if ($child->hasChildren()) {
                $tbody->add($this->renderNode($bp, $child, $this->getCurrentPath()));
            } else {
                $this->renderChild($bp, $tbody, $child, $path);
            }
        }

        return $table;
    }

    protected function renderChild($bp, BaseHtmlElement $ul, $node, $path = null)
    {
        $li = Html::tag('li', [
            'class'             => 'movable',
            'id'                => $this->getId($node, $path ?: []),
            'data-node-name'    => (string) $node
        ]);
        $ul->add($li);

        if ($node instanceof BpNode && $node->hasInfoUrl()) {
            $li->add($this->createInfoAction($node));
        }

        if (! $this->isLocked()) {
            $li->add($this->getActionIcons($bp, $node));
        }

        $link = $node->getLink();
        $link->getAttributes()->set('data-base-target', '_next');
        $link->add($this->getNodeIcons($node));

        if ($node->hasChildren()) {
            $link->add($this->renderStateBadges($node->getStateSummary()));
        }

        if ($time = $node->getLastStateChange()) {
            $since = $this->timeSince($time)->prepend(
                sprintf(' (%s ', $node->getStateName())
            )->add(')');
            $link->add($since);
        }

        $li->add($link);
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
            'wrench',
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
        );
    }

    protected function actionIcon($icon, $url, $title)
    {
        return Html::tag(
            'a',
            [
                'href'  => $url,
                'title' => $title,
                'style' => 'float: right'
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
