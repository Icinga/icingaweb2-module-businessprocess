<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Icon;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Html\Text;
use Icinga\Module\Businessprocess\Web\Url;

class BpDashboardTile extends BaseElement
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'dashboard-tile'];

    public function __construct(BpConfig $bp, $title, $description, $icon, $url, $urlParams = null, $attributes = null)
    {
        $this->add(
            Container::create(
                ['class' => 'bp-link'],
                Link::create(
                    Icon::create($icon),
                    $url,
                    $urlParams,
                    $attributes
                )->add(
                    Element::create('span', array('class' => 'header'))->addContent($title)
                )->addContent($description)
            )
        );

        $tiles = Container::create(['class' => 'bp-root-tiles']);

        foreach ($bp->getChildren() as $node) {
            $state = strtolower($node->getStateName());

            $tiles->add(
                Link::create(
                    Text::create('&nbsp;')->setEscaped(),
                    $url,
                    $urlParams + ['node' => $node->getName()],
                    ['class' => "badge state-{$state}", 'title' => $node->getAlias()]
                )
            );
        }

        $this->add($tiles);
    }
}
