<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Renderer\TileRenderer\AddNewTile;
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
            $this->add(new AddNewTile($this));
        }

        $path = $this->getCurrentPath();
        foreach ($nodes as $name => $node) {
            $this->add(new NodeTile($this, $name, $node, $path));
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
        $count = $this->bp->countChildren();
        $howMany = 'normal';

        if ($count < 20) {
            $howMany = 'few';
        } elseif ($count > 50) {
            $howMany = 'many';
        }

        return $howMany;
    }
}
