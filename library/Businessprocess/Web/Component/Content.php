<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use ipl\Html\BaseHtmlElement;

class Content extends BaseHtmlElement
{
    protected $tag = 'div';

    protected $contentSeparator = "\n";

    protected $defaultAttributes = array('class' => 'content');
}
