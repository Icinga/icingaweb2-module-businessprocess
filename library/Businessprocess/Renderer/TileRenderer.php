<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\ImportedNode;
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
                'data-sortable-direction'       => 'horizontal', // Otherwise movement is buggy on small lists
                'data-csrf-token'               => CsrfToken::generate()
            ]
        );

        if ($this->wantsRootNodes()) {
            $nodesDiv->getAttributes()->add(
                'data-action-url',
                $this->getUrl()->with(['config' => $bp->getName()])->getAbsoluteUrl()
            );
        } else {
            $nodeName = $this->parent instanceof ImportedNode
                ? $this->parent->getNodeName()
                : $this->parent->getName();
            $nodesDiv->getAttributes()
                ->add('data-node-name', $nodeName)
                ->add('data-action-url', $this->getUrl()
                    ->with([
                        'config'    => $this->parent->getBpConfig()->getName(),
                        'node'      => $nodeName
                    ])
                    ->getAbsoluteUrl());
        }

        $nodes = $this->getChildNodes();

        $path = $this->getCurrentPath();
        foreach ($nodes as $name => $node) {
            $this->add(new NodeTile($this, $node, $path));
        }

        if ($this->wantsRootNodes()) {
            $unbound = $this->createUnboundParent($bp);
            if ($unbound->hasChildren()) {
                $this->add(new NodeTile($this, $unbound));
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
