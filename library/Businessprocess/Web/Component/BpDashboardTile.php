<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Module\Businessprocess\BpConfig;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\Text;
use ipl\Web\Url;
use ipl\Web\Widget\Icon;
use ipl\Web\Widget\Link;

class BpDashboardTile extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'dashboard-tile'];

    public function __construct(BpConfig $bp, $title, $description, $icon, $url, $urlParams = null, $attributes = null)
    {
        $this->add(Html::tag(
            'div',
            ['class' => 'bp-link', 'data-base-target' => '_main'],
            (new Link(new Icon($icon), Url::fromPath($url, $urlParams ?: []), $attributes))
                ->add(Html::tag('span', ['class' => ['header', 'text'], 'title' => $title], $title))
                ->add(Html::tag('span', ['class' => 'text', 'title' => $description], $description))
        ));

        $tiles = Html::tag('div', ['class' => 'bp-root-tiles']);

        foreach ($bp->getChildren() as $node) {
            $state = strtolower($node->getStateName());
            if ($node->isHandled()) {
                $state .= ' handled';
            }

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
