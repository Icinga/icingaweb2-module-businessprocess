<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Link;
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

        foreach ($nodes as $name => $node) {
            $this->add(new NodeTile($this, $name, $node));
        }

        $nodesDiv->addContent($this->getContent());
            $this->setContent($this->renderBreadCrumb())
                ->addContent($nodesDiv);

        return parent::render();
    }

    public function renderBreadCrumb()
    {
        $breadcrumb = Element::create('ul', array(
            'class'            => 'breadcrumb',
            'data-base-target' => '_main'
        ));

        $breadcrumb->add(Element::create('li')->add(
            Link::create($this->bp->getTitle(), $this->getBaseUrl())
        ));
        $bp = $this->bp;
        $path = $this->getMyPath();
        $max = 20;
        $chosen = array();
        for ($i = 1; $i <= $max; $i++) {
            if (! empty($path)) {
                $chosen[] = array_pop($path);
            }
        }
        $chosen = array_reverse($chosen);
        $consumed = array();
        while ($parent = array_shift($chosen)) {
            $breadcrumb->add($this->renderParent($bp->getNode($parent), $consumed));
            $consumed[] = $parent;
        }

        return $breadcrumb;
    }

    /**
     * @param BpNode $parent
     */
    public function renderParent(BpNode $parent, $path)
    {
        $p = new NodeTile($this, (string) $parent, $parent, $path);
        $p->attributes()->add('class', $this->getNodeClasses($parent));
        $p->setTag('li');
        return $p;
    }

    /**
     * @return array
     */
    public function getDefaultAttributes()
    {
        return array(
            'class' => 'tiles aaaa' . $this->howMany()
        );
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
