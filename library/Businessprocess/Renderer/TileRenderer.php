<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\Renderer\TileRenderer\NodeTile;
use Icinga\Module\Businessprocess\Web\Form\CsrfToken;
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
                'class'                         => ['sortable', 'tiles', $this->howMany()],
                'data-base-target'              => '_next',
                'data-sortable-disabled'        => $this->isLocked() ? 'true' : 'false',
                'data-sortable-data-id-attr'    => 'id',
                'data-sortable-filter'          => '.addnew',
                'data-sortable-direction'       => 'horizontal', // Otherwise movement is buggy on small lists
                'data-csrf-token'               => CsrfToken::generate(),
                'data-action-url'               => $this->getUrl()->getAbsoluteUrl()
            ]
        );
        if (! $this->wantsRootNodes()) {
            $nodesDiv->getAttributes()->add('data-node-name', $this->parent->getName());
        }

        $nodes = $this->getChildNodes();

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
}
