<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Renderer\TileRenderer\NodeTile;
use Icinga\Module\Businessprocess\Web\Url;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Web\Widget\Icon;

class Breadcrumb extends BaseHtmlElement
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

        $breadcrumb->add(Html::tag('li')->add(
            Html::tag(
                'a',
                [
                    'href'  => Url::fromPath('businessprocess'),
                    'title' => mt('businessprocess', 'Show Overview')
                ],
                new Icon('house')
            )
        ));
        $breadcrumb->add(Html::tag('li')->add(
            Html::tag('a', ['href' => $bpUrl], $bp->getTitle())
        ));
        $path = $renderer->getCurrentPath();

        $parts = array();
        while ($nodeName = array_pop($path)) {
            $node = $bp->getNode($nodeName);
            $renderer->setParentNode($node);
            array_unshift(
                $parts,
                static::renderNode($node, $path, $renderer)
            );
        }
        $breadcrumb->add($parts);

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
        $p = new NodeTile($renderer, $node, $path);
        $p->setTag('li');
        return $p;
    }
}
