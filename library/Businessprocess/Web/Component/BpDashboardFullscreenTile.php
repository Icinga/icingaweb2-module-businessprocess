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

    protected $defaultAttributes = ['class' => 'dashboard-tile'];

    protected $bp;

    protected $title;

    protected $description;

    public function __construct(BpConfig $bp, $title, $description)
    {
        $this->bp = $bp;
        $this->title = $title;
        $this->description = $description;
    }

    protected function assemble()
    {
        $baseUrl = Url::fromPath('businessprocess/process/show', ['config' => $this->bp->getName()]);
        $this->add(Html::tag(
            'div',
            ['class' => 'bp-link', 'data-base-target' => '_main'],
            Html::tag('a', ['href' => $baseUrl])
                ->add(Html::tag('span', ['class' => 'header'], $this->title))
                ->add($this->description)
        ));

        $tiles = Html::tag('div', ['class' => 'bp-root-tiles-fullscreen']);

        foreach ($this->bp->getChildren() as $node) {
            $state = strtolower($node->getStateName());

            $tiles->add(Html::tag(
                'a',
                [
                    'href'  => $baseUrl->with(['node' => $node->getName()]),
                    'class' => "badge badge-fullscreen state-{$state}",
                    'title' => $node->getAlias()
                ],
                Text::create($node->getAlias())
            ));
        }

        $this->add($tiles);
    }
}
