<?php

use Icinga\Module\Businessprocess\Controller;

class Businessprocess_IndexController extends Controller
{
    /**
     * Show a welcome page if no process is available
     */
    public function indexAction()
    {
        $this->tabs()->add('welcome', array(
            'label' => $this->translate('Business Processes'),
            'url'   => $this->getRequest()->getUrl()
        ))->activate('welcome');

        $configs = $this->storage()->listProcesses();

        if (! empty($configs)) {
            // Redirect to show the first process if there is any
            $this->redirectNow(
                'businessprocess/process/show',
                array('config' => key($configs))
            );
        }

        // Check back from time to time, maybe someone created a process
        $this->setAutorefreshInterval(30);
    }
}
