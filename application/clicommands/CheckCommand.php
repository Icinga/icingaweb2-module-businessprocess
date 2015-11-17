<?php

namespace Icinga\Module\Businessprocess\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class CheckCommand extends Command
{
    protected $storage;

    public function init()
    {
        $this->storage = new LegacyStorage($this->Config()->getSection('global'));
    }

    /**
     * Check a specific process
     *
     * USAGE
     *
     * icingacli businessprocess check process [--config <name>] <process>
     */
    public function processAction()
    {
        $name = $this->params->get('config');
        if ($name === null) {
            $name = $this->getFirstProcessName();
        }

        $bp = $this->storage->loadProcess($name);

        if (null !== ($stateType = $this->params->get('state-type'))) {
            if ($stateType === 'soft') {
                $bp->useSoftStates();
            }
            if ($stateType === 'hard') {
                $bp->useHardStates();
            }
        }

        $node = $bp->getNode($this->params->shift());
        $bp->retrieveStatesFromBackend();
        if ($bp->hasErrors()) {
            printf(
                "Checking Business Process %s failed: %s\n",
                $node->getAlias(),
                implode("\n", $bp->getErrors())
            );
            exit(3);
        }
        printf("Business Process %s: %s\n", $node->getStateName(), $node->getAlias());
        exit($node->getState());
    }

    protected function getFirstProcessName()
    {
        $list = $this->storage->listProcesses();
        return key($list);
    }
}
