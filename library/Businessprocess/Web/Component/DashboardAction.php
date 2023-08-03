<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Web\Url;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Web\Widget\Icon;

class DashboardAction extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $defaultAttributes = array('class' => 'action');

    public function __construct($title, $description, $icon, $url, $urlParams = null, $attributes = null)
    {
        if (! isset($attributes['href'])) {
            $attributes['href'] = Url::fromPath($url, $urlParams ?: []);
        }

        $this->add(Html::tag('a', $attributes)
            ->add(new Icon($icon))
            ->add(Html::tag('span', ['class' => 'header'], $title))
            ->add($description));
    }
}
