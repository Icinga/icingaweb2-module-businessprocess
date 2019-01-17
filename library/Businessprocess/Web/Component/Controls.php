<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use ipl\Html\BaseHtmlElement;

class Controls extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $contentSeparator = "\n";

    protected $defaultAttributes = array('class' => 'controls');
}
