<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BusinessProcess;
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
        $bp = $this->bp;
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
     * @param BusinessProcess $bp
     * @return string
     */
    public function renderBp(BusinessProcess $bp)
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
     * @param BusinessProcess $bp
     * @param Node $node
     * @param array $path
     *
     * @return string
     */
    public function renderNode(BusinessProcess $bp, Node $node, $path = array())
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
        if ($node->hasChildren()) {
            $attributes->add('class', 'operator');
        } else {
            $attributes->add('class', 'node');
        }

        $tbody = $table->createElement('tbody');
        $tr =  $tbody->createElement('tr');

        if ($node->hasChildren()) {
            $tr->createElement(
                'th',
                array(
                    'rowspan' => $node->countChildren() + 1
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

        if (! $this->bp->isLocked()) {
            $td->addContent($this->getActionIcons($bp, $node));
        }

        $link = $node->getLink();
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

        return $table;
    }

    protected function getActionIcons(BusinessProcess $bp, Node $node)
    {
        if ($node instanceof BpNode) {
            return $this->createEditAction($bp, $node);
        } else {
            return $this->createSimulationAction($bp, $node);
        }
    }

    protected function createEditAction(BusinessProcess $bp, BpNode $node)
    {
        return $this->actionIcon(
            'wrench',
            Url::fromPath('businessprocess/node/edit', array(
                'config' => $bp->getName(),
                'node'   => $node->getName()
            )),
            $this->translate('Modify this node')
        );
    }

    protected function createSimulationAction(BusinessProcess $bp, Node $node)
    {
        return $this->actionIcon(
            'magic',
            Url::fromPath('businessprocess/process/show?addSimulation&unlocked', array(
                'config' => $bp->getName(),
                'node' => $node->getName()
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
}
