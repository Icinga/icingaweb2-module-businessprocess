<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use ipl\Html\BaseHtmlElement;

class ActionBar extends BaseHtmlElement
{
    protected $contentSeparator = ' ';

    /** @var string */
    protected $tag = 'div';

    protected $defaultAttributes = array('class' => 'action-bar');
}
