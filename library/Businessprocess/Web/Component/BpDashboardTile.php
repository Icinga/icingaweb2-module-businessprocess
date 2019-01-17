<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Web\Url;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\Text;

class BpDashboardTile extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'dashboard-tile'];

    public function __construct(BpConfig $bp, $title, $description, $icon, $url, $urlParams = null, $attributes = null)
    {
        if (! isset($attributes['href'])) {
            $attributes['href'] = Url::fromPath($url, $urlParams ?: []);
        }

        $this->add(Html::tag(
            'div',
            ['class' => 'bp-link', 'data-base-target' => '_main'],
            Html::tag('a', $attributes, Html::tag('i', ['class' => 'icon icon-' . $icon]))
                ->add(Html::tag('span', ['class' => 'header'], $title))
                ->add($description)
        ));

        $tiles = Html::tag('div', ['class' => 'bp-root-tiles']);

        foreach ($bp->getChildren() as $node) {
            $state = strtolower($node->getStateName());

            $tiles->add(Html::tag(
                'a',
                [
                    'href'  => Url::fromPath($url, $urlParams ?: [])->with(['node' => $node->getName()]),
                    'class' => "badge state-{$state}",
                    'title' => $node->getAlias()
                ],
                Text::create('&nbsp;')->setEscaped()
            ));
        }

        $this->add($tiles);
    }
}
