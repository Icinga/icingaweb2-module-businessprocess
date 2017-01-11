<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Icon;
use Icinga\Module\Businessprocess\Html\Link;

class DashboardAction extends BaseElement
{
    protected $tag = 'div';

    protected $defaultAttributes = array('class' => 'action');

    public function __construct($title, $description, $icon, $url, $urlParams = null, $attributes = null)
    {
        $this->add(
            Link::create(
                Icon::create($icon),
                $url,
                $urlParams,
                $attributes
            )->add(
                Element::create('span', array('class' => 'header'))->addContent($title)
            )->addContent($description)
        );
    }
}
