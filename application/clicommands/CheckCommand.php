<?php

namespace Icinga\Module\Businessprocess\Clicommands;

class CheckCommand extends ProcessCommand
{
    public function listActions()
    {
        return array('process');
    }

    /**
     * 'check process' is DEPRECATED, please use 'process check' instead
     *
     * USAGE
     *
     * icingacli businessprocess check process [--config <name>] <process>
     */
    public function processAction()
    {
        $this->checkAction();
    }
}
