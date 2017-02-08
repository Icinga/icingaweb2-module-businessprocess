<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\Html\Container;
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
        $bp = $this->config;
        $nodesDiv = Container::create(
            array(
                'class' => array(
                    'tiles',
                    $this->howMany()
                ),
                'data-base-target' => '_self',
            )
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
        $div = Container::create(
            array('class' => 'addnew')
        );

        $actions = Container::create(
            array(
                'class'            => 'actions',
                'data-base-target' => '_self'
            )
        );

        $link = Link::create(
            $this->translate('Add'),
            $this->getUrl()->with('action', 'add'),
            null,
            array(
                'title' => $this->translate('Add a new business process node')
            )
        );
        $actions->add(
            Link::create(
                Icon::create('plus'),
                $this->getUrl()->with('action', 'add'),
                null,
                array(
                    'title' => $this->translate('Add a new business process node')
                )
            )
        );

        return $div->add($actions)->add($link);
    }
}
