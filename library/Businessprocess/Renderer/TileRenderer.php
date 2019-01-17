<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\Renderer\TileRenderer\NodeTile;
use ipl\Html\Html;

class TileRenderer extends Renderer
{
    /**
     * @inheritdoc
     */
    public function render()
    {
        $bp = $this->config;
        $nodesDiv = Html::tag(
            'div',
            [
                'class'             => ['tiles', $this->howMany()],
                'data-base-target'  => '_next'
            ]
        );

        $nodes = $this->getChildNodes();

        if (! $this->isLocked() && count($nodes) > 8
            && $this->config->getMetadata()->canModify()
        ) {
            $this->add($this->addNewNode());
        }

        $path = $this->getCurrentPath();
        foreach ($nodes as $name => $node) {
            $this->add(new NodeTile($this, $name, $node, $path));
        }

        if ($this->wantsRootNodes()) {
            $unbound = $this->createUnboundParent($bp);
            if ($unbound->hasChildren()) {
                $name = $unbound->getName();
                $this->add(new NodeTile($this, $name, $unbound));
            }
        }

        if (! $this->isLocked() && $this->config->getMetadata()->canModify()) {
            $this->add($this->addNewNode());
        }

        $nodesDiv->add($this->getContent());
        $this->setContent($nodesDiv);

        return parent::render();
    }

    /**
     * A CSS class giving a rough indication of how many nodes we have
     *
     * This is used to show larger tiles when there are few and smaller
     * ones if there are many.
     *
     * @return string
     */
    protected function howMany()
    {
        $count = $this->countChildNodes();
        $howMany = 'normal';

        if ($count <= 6) {
            $howMany = 'few';
        } elseif ($count > 12) {
            $howMany = 'many';
        }

        return $howMany;
    }

    protected function addNewNode()
    {
        $div = Html::tag('div', ['class' => 'addnew', 'data-base-target' => '_self']);

        $actions = Html::tag('div', ['class'=> 'actions']);

        $link = Html::tag(
            'a',
            [
                'href'  => $this->getUrl()->with('action', 'add'),
                'title' => mt('businessprocess', 'Add a new business process node')
            ],
            mt('businessprocess', 'Add')
        );
        $actions->add(
            Html::tag(
                'a',
                [
                    'href'  => $this->getUrl()->with('action', 'add'),
                    'title' => mt('businessprocess', 'Add a new business process node')
                ],
                Html::tag('i', ['class' => 'icon icon-plus'])
            )
        );

        return $div->add($actions)->add($link);
    }
}
