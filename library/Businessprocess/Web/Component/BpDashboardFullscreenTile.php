<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Web\Url;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\Text;

class BpDashboardFullscreenTile extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'dashboard-tile dashboard-tile-fullscreen'];

    public function __construct(BpConfig $bp, $title, $description, $icon, $url, $urlParams = null, $attributes = null)
    {
        if (! isset($attributes['href'])) {
            $attributes['href'] = Url::fromPath($url, $urlParams ?: []);
        }

        $this->add(Html::tag(
            'div',
            ['class' => 'bp-link', 'data-base-target' => '_main'],
            Html::tag('a', $attributes)
                ->add(Html::tag('span', ['class' => 'header'], $title))
                ->add($description)
        ));

        $tiles = Html::tag('div', ['class' => 'bp-root-tiles-fullscreen']);

        foreach ($bp->getChildren() as $node) {
            $state = strtolower($node->getStateName());

            $tiles->add(Html::tag(
                'a',
                [
                    'href'  => Url::fromPath($url, $urlParams ?: [])->with(['node' => $node->getName()]),
                    'class' => "badge badge-fullscreen state-{$state}",
                    'title' => $node->getAlias()
                ],
                Text::create($node->getAlias())->setEscaped()
            ));
        }

        $this->add($tiles);
    }
}
