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
        $this->view->dashboard = Dashboard::create($this->Auth(), $this->storage());
        $this->view->tabs = $this->overviewTab();
        $this->setAutorefreshInterval(15);
    }
}
