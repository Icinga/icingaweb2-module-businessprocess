<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Date\DateFormatter;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Form\CsrfToken;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Web\Widget\StateBall;

class TreeRenderer extends Renderer
{
    /**
     * @inheritdoc
     */
    public function render()
    {
        $bp = $this->config;
        $htmlId = $bp->getHtmlId();
        $this->add(Html::tag(
            'ul',
            [
                'id'                            => $htmlId,
                'class'                         => ['bp', 'sortable'],
                'data-sortable-disabled'        => $this->isLocked() ? 'true' : 'false',
                'data-sortable-data-id-attr'    => 'id',
                'data-sortable-direction'       => 'vertical',
                'data-sortable-group'           => json_encode([
                    'name'  => $this->wantsRootNodes() ? 'root' : $htmlId,
                    'put'   => 'function:rowPutAllowed'
                ]),
                'data-sortable-invert-swap'     => 'true',
                'data-is-root-config'           => $this->wantsRootNodes() ? 'true' : 'false',
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
            if ($node instanceof BpNode) {
                $html[] = $this->renderNode($bp, $node);
            } else {
                $html[] = $this->renderChild($bp, $node);
            }
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
     * @param array $path
     * @return BaseHtmlElement[]
     */
    public function getNodeIcons(Node $node, array $path = null)
    {
        $icons = [];
        if (empty($path)) {
            $icons[] = Html::tag('i', ['class' => 'icon icon-sitemap']);
        } else {
            $icons[] = $node->getIcon();
        }
        $icons[] = (new StateBall(strtolower($node->getStateName())))->addAttributes([
            'title' => sprintf(
                '%s %s',
                $node->getStateName(),
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
                'data-node-name'    => $node->getName()
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

        if ($node instanceof BpNode && $node->hasInfoUrl()) {
            $div->add($this->createInfoAction($node));
        }

        $div->add(Html::tag('span', null, $node->getAlias()));

        if ($node instanceof BpNode) {
            $div->add(Html::tag('span', ['class' => 'op'], $node->operatorHtml()));
        }

        if (! $this->isLocked()) {
            $div->add($this->getActionIcons($bp, $node));
        }

        $ul = Html::tag('ul', [
            'class'                         => ['bp', 'sortable'],
            'data-sortable-disabled'        => $this->isLocked() ? 'true' : 'false',
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
                ->overwriteParams([
                    'config'    => $node->getBusinessProcess()->getName(),
                    'node'      => $node instanceof ImportedNode
                        ? $node->getNodeName()
                        : (string) $node
                ])
                ->getAbsoluteUrl()
        ]);
        $li->add($ul);

        $path[] = (string) $node;
        foreach ($node->getChildren() as $name => $child) {
            if ($child instanceof BpNode) {
                $ul->add($this->renderNode($bp, $child, $path));
            } else {
                $ul->add($this->renderChild($bp, $child, $path));
            }
        }

        return $li;
    }

    protected function renderChild($bp, Node $node, $path = null)
    {
        $li = Html::tag('li', [
            'class'             => 'movable',
            'id'                => $this->getId($node, $path ?: []),
            'data-node-name'    => (string) $node
        ]);

        $li->add($this->getNodeIcons($node, $path));

        $link = $node->getLink();
        $link->getAttributes()->set('data-base-target', '_next');
        $li->add($link);

        if (! $this->isLocked()) {
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
        );
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
