<?php

namespace Icinga\Module\Businessprocess\Renderer\TileRenderer;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\HostNode;
use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\HtmlString;
use Icinga\Module\Businessprocess\Html\Icon;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\MonitoredNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\ServiceNode;

class NodeTile extends BaseElement
{
    protected $tag = 'div';

    /**
     * NodeTile constructor.
     * @param Renderer $renderer
     * @param $name
     * @param Node $node
     * @param null $path
     */
    public function __construct(Renderer $renderer, $name, Node $node, $path = null)
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

        if ($node instanceof ServiceNode) {
            $link = Link::create(
                Icon::create('service'),
                $url
            )->addContent($node->getHostname())
                ->addContent(HtmlString::create('<br />'))
                ->addContent($node->getServiceDescription());
        } elseif ($node instanceof HostNode) {
                $link = Link::create(
                    Icon::create('host'),
                    $url
                )->addContent($node->getHostname());
        } else {
            $link = Link::create($node->getAlias(), $url);
        }

        $this->add($link);
        if ($node instanceof BpNode) {
            $link->addContent($renderer->renderStateBadges($node->getStateSummary()));
        }
    }
}
