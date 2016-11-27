<?php

namespace Icinga\Module\Businessprocess\Renderer\TileRenderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\MonitoredNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Renderer\TileRenderer;

class NodeTile extends BaseElement
{
    protected $tag = 'div';

    public function __construct(TileRenderer $renderer, $name, Node $node, $path = null)
    {
        $attributes = $this->attributes();
        $attributes->add('class', $renderer->getNodeClasses($node));
        $attributes->add('id', 'bp-' . (string) $node);

        if ($node instanceof MonitoredNode) {
            $attributes->add('data-base-target', '_next');
            $url = $node->getUrl();
        } else {
            $bp = $renderer->getBusinessProcess();
            $params = array(
                'config' => $node instanceof ImportedNode ?
                    $node->getConfigName() :
                    $bp->getName()
            );

            if ($name !== null) {
                $params['node'] = $name;
            }

            $url = $renderer->getBaseUrl();
            $p = $url->getParams();
            $p->mergeValues($params);
            if (! empty($path)) {
                $p->addValues('path', $path);
            }
        }

        $link = Link::create($node->getAlias(), $url);

        $this->add($link);
        if ($node instanceof BpNode) {
            $link->addContent($renderer->renderStateBadges($node->getStateSummary()));
        }
    }
}