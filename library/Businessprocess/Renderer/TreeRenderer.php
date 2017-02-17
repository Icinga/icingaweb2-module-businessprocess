<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Icon;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Url;

class TreeRenderer extends Renderer
{
    /**
     * @inheritdoc
     */
    public function render()
    {
        $bp = $this->config;
        $this->add(Container::create(
            array(
                'id'    => $bp->getHtmlId(),
                'class' => 'bp'
            ),
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
     * @return Icon[]
     */
    public function getNodeIcons(Node $node)
    {
        $icons = array();
        if ($node->isInDowntime()) {
            $icons[] = Icon::create('moon');
        }
        if ($node->isAcknowledged()) {
            $icons[] = Icon::create('ok');
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
        $table = Element::create(
            'table',
            array(
                'id' => $this->getId($node, $path),
                'class' => array(
                    'bp',
                    $node->getObjectClassName()
                )
            )
        );
        $attributes = $table->attributes();
        $attributes->add('class', $this->getStateClassNames($node));
        if ($node->isHandled()) {
            $attributes->add('class', 'handled');
        }
        if ($node instanceof BpNode) {
            $attributes->add('class', 'operator');
        } else {
            $attributes->add('class', 'node');
        }

        $tbody = $table->createElement('tbody');
        $tr =  $tbody->createElement('tr');

        if ($node instanceof BpNode) {
            $tr->createElement(
                'th',
                array(
                    'rowspan' => $node->countChildren() + 1 + ($this->isLocked() ? 0 : 1)
                )
            )->createElement(
                'span',
                array('class' => 'op')
            )->setContent($node->operatorHtml());
        }
        $td = $tr->createElement('td');

        if ($node instanceof BpNode && $node->hasInfoUrl()) {
            $td->add($this->createInfoAction($node));
        }

        if (! $this->isLocked()) {
            $td->addContent($this->getActionIcons($bp, $node));
        }

        $link = $node->getLink();
        $link->attributes()->set('data-base-target', '_next');
        $link->addContent($this->getNodeIcons($node));

        if ($node->hasChildren()) {
            $link->addContent($this->renderStateBadges($node->getStateSummary()));
        }

        if ($time = $node->getLastStateChange()) {
            $since = $this->timeSince($time)->prependContent(
                sprintf(' (%s ', $node->getStateName())
            )->addContent(')');
            $link->addContent($since);
        }

        $td->addContent($link);

        foreach ($node->getChildren() as $name => $child) {
            $tbody->createElement('tr')->createElement('td')->setContent(
                $this->renderNode($bp, $child, $this->getCurrentPath())
            );
        }

        if (! $this->isLocked() && $node instanceof BpNode && $bp->getMetadata()->canModify()) {
            $tbody->createElement('tr')->createElement('td')->setContent(
                $this->renderAddNewNode($node)
            );
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
            $this->translate('Modify this node')
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
            $this->translate('Simulate a specific state')
        );
    }

    protected function createInfoAction(BpNode $node)
    {
        $url = $node->getInfoUrl();
        return $this->actionIcon(
            'help',
            $url,
            sprintf('%s: %s', $this->translate('More information'), $url)
        );
    }

    protected function actionIcon($icon, $url, $title)
    {
        return Link::create(
            Icon::create($icon),
            $url,
            null,
            array(
                'title' => $title,
                'style' => 'float: right',
            )
        );
    }

    protected function renderAddNewNode($parent)
    {
        return Link::create(
            $this->translate('Add'),
            $this->getUrl()->with('action', 'add')->with('node', $parent->getName()),
            null,
            array(
                'class' => 'addnew icon-plus',
                'title' => $this->translate('Add a new business process node')
            )
        );
    }
}
