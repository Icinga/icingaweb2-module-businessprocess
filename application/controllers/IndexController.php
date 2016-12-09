<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Web\Controller;

class IndexController extends Controller
{
    /**
     * Show a welcome page if no process is available
     */
    public function indexAction()
    {
        $configs = $this->storage()->listProcesses();

        if (! empty($configs)) {
            // Redirect to show the first process if there is any
            $this->redirectNow(
                'businessprocess/process/show?mode=tile',
                array('config' => key($configs))
            );
        }
        $this->tabs()->add('welcome', array(
            'label' => $this->translate('Business Processes'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('welcome');

        // Check back from time to time, maybe someone created a process
        $this->setAutorefreshInterval(30);
    }
}
