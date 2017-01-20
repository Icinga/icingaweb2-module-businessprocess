<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Web\Widget\Tabs;

/**
 * Class WtfTabs
 *
 * TODO: Please remove this as soon as we drop support for PHP 5.3.x
 *       This works around https://bugs.php.net/bug.php?id=43200 and fixes
 *       https://github.com/Icinga/icingaweb2-module-businessprocess/issues/81
 *
 * @package Icinga\Module\Businessprocess\Web\Component
 */
class WtfTabs extends Tabs
{
    public function render()
    {
        return parent::render();
    }
}
