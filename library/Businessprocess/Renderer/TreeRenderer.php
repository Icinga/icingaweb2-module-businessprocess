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
            'div',
            [
                'id'                            => $bp->getHtmlId(),
                'class'                         => ['bp', 'sortable'],
                'data-sortable-disabled'        => $this->isLocked(),
                'data-sortable-data-id-attr'    => 'id',
                'data-sortable-direction'       => 'vertical',
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
            'table',
            [
                'id'                => $this->getId($node, $path),
                'class'             => ['bp', $node->getObjectClassName()],
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

        $tbody = Html::tag('tbody', [
            'class'                         => 'sortable',
            'data-sortable-disabled'        => $this->isLocked(),
            'data-sortable-data-id-attr'    => 'id',
            'data-sortable-draggable'       => '.movable',
            'data-sortable-direction'       => 'vertical',
            'data-csrf-token'               => CsrfToken::generate(),
            'data-action-url'               => $this->getUrl()
                ->overwriteParams(['node' => (string) $node])
                ->getAbsoluteUrl()
        ]);
        $table->add($tbody);
        $tr =  Html::tag('tr');
        $tbody->add($tr);

        if ($node instanceof BpNode) {
            $tr->add(Html::tag(
                'th',
                ['rowspan' => $node->countChildren() + 1 + ($this->isLocked() ? 0 : 1)],
                Html::tag('span', ['class' => 'op'], $node->operatorHtml())
            ));
        }
        $td = Html::tag('td');
        $tr->add($td);

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

        $path[] = (string) $node;
        foreach ($node->getChildren() as $name => $child) {
            $tbody->add(Html::tag(
                'tr',
                [
                    'class'             => 'movable',
                    'id'                => $this->getId($child, $path),
                    'data-node-name'    => $name
                ],
                Html::tag(
                    'td',
                    null,
                    $this->renderNode($bp, $child, $this->getCurrentPath())
                )
            ));
        }

        if (! $this->isLocked() && $node instanceof BpNode && $bp->getMetadata()->canModify()) {
            $tbody->add(Html::tag(
                'tr',
                null,
                Html::tag(
                    'td',
                    null,
                    $this->renderAddNewNode($node)
                )
            ));
        }

        return $table;
    }

    protected function getActionIcons(BpConfig $bp, Node $node)
    {
        if ($node instanceof BpNode) {
            if ($bp->getMetadata()->canModify()) {
                return $this->createEditAction($bp, $node);
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
        return Html::tag(
            'a',
            [
                'href'  => $this->getUrl()
                    ->with('action', 'add')
                    ->with('node', $parent->getName()),
                'title' => mt('businessprocess', 'Add a new business process node'),
                'class' => 'addnew icon-plus'
            ],
            mt('businessprocess', 'Add')
        );
    }
}
