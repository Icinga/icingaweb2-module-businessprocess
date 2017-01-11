<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Businessprocess\Web\Component\Dashboard;

class IndexController extends Controller
{
    /**
     * Show an overview page
     */
    public function indexAction()
    {
        $this->setTitle($this->translate('Business Process Overview'));
        $this->controls()->add($this->overviewTab());
        $this->content()->add(Dashboard::create($this->Auth(), $this->storage()));
        $this->setAutorefreshInterval(15);
    }
}
