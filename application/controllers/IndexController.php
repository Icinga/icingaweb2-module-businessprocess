<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Businessprocess\Web\Component\Dashboard;
use Icinga\Module\Businessprocess\Web\Component\DashboardFullscreen;

class IndexController extends Controller
{
    /**
     * Show an overview page
     */
    public function indexAction()
    {
        $this->setTitle($this->translate('Business Process Overview'));
        if (!$this->showCompact) {
            $this->controls()->add($this->overviewTab());
        }
        if ($this->showFullscreen) {
            $this->content()->add(DashboardFullscreen::create($this->Auth(), $this->storage()));
            $this->setAutorefreshInterval(120);
            $this->controls()->getAttributes()->add('class', 'want-fullscreen');
        } else {
            $this->content()->add(Dashboard::create($this->Auth(), $this->storage()));
            $this->setAutorefreshInterval(15);
        }
    }
}
