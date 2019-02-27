<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use ipl\Html\BaseHtmlElement;

class StateBall extends BaseHtmlElement
{
    const SIZE_TINY = 'xs';
    const SIZE_SMALL = 's';
    const SIZE_MEDIUM = 'm';
    const SIZE_LARGE = 'l';

    protected $tag = 'div';

    public function __construct($state = 'none', $size = self::SIZE_SMALL)
    {
        $state = \trim($state);

        if (empty($state)) {
            $state = 'none';
        }

        $size = \trim($size);

        if (empty($size)) {
            $size = self::SIZE_MEDIUM;
        }

        $this->defaultAttributes = ['class' => "state-ball state-$state size-$size"];
    }
}
