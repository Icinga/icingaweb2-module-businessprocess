<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Icon;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Renderer\TileRenderer\NodeTile;

class TileRenderer extends Renderer
{
    /**
     * @inheritdoc
     */
    public function render()
    {
        $bp = $this->bp;
        $nodesDiv = Container::create(
            array(
                'class' => array(
                    'tiles',
                    $this->howMany()
                ),
                'data-base-target' => '_main',
            )
        );

        if ($this->wantsRootNodes()) {
            $nodes = $bp->getChildren();
        } else {
            $nodes = $this->parent->getChildren();
        }

        if (! $this->isLocked()) {
            $this->add($this->addNewNode());
        }

        $path = $this->getCurrentPath();
        foreach ($nodes as $name => $node) {
            $this->add(new NodeTile($this, $name, $node, $path));
        }

        $unbound = $this->createUnboundParent($bp);
        if ($unbound->hasChildren()) {
            $name = $unbound->getAlias();
            $this->add($this->add(new NodeTile($this, $name, $unbound)));
        }

        $nodesDiv->addContent($this->getContent());
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
        return Element::create(
            'div',
            array('class' => 'addnew')
        )->add(
            Link::create(
                Icon::create('plus'),
                $this->getUrl()->with('action', 'add'),
                null,
                array(
                    'title' => $this->translate('Add a new business process node')
                )
            )->addContent($this->translate('Add'))
        );
    }
}
