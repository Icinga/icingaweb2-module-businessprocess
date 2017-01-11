<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Renderer\TileRenderer\NodeTile;

class Breadcrumb extends BaseElement
{
    protected $tag = 'ul';

    protected $defaultAttributes = array(
        'class'            => 'breadcrumb',
        'data-base-target' => '_main'
    );

    /**
     * @param Renderer $renderer
     * @return static
     */
    public static function create(Renderer $renderer)
    {
        $bp = $renderer->getBusinessProcess();
        $breadcrumb = new static;
        $bpUrl = $renderer->getBaseUrl();
        if ($bpUrl->getParam('action') === 'delete') {
            $bpUrl->remove('action');
        }
        $breadcrumb->add(Element::create('li')->add(
            Link::create($bp->getTitle(), $bpUrl)
        ));
        $path = $renderer->getCurrentPath();

        $parts = array();
        while ($node = array_pop($path)) {
            array_unshift(
                $parts,
                static::renderNode($bp->getNode($node), $path, $renderer)
            );
        }
        $breadcrumb->addContent($parts);

        return $breadcrumb;
    }

    /**
     * @param BpNode $node
     * @param array $path
     * @param Renderer $renderer
     *
     * @return NodeTile
     */
    protected static function renderNode(BpNode $node, $path, Renderer $renderer)
    {
        // TODO: something more generic than NodeTile?
        $renderer = clone($renderer);
        $renderer->lock()->setIsBreadcrumb();
        $p = new NodeTile($renderer, (string) $node, $node, $path);
        $p->attributes()->add('class', $renderer->getNodeClasses($node));
        $p->setTag('li');
        return $p;
    }
}
