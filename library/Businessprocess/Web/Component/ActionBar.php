<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Module\Businessprocess\Html\BaseElement;

class ActionBar extends BaseElement
{
    protected $contentSeparator = ' ';

    /** @var string */
    protected $tag = 'div';

    protected $defaultAttributes = array('class' => 'action-bar');
}
