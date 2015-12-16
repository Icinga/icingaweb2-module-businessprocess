<?php

namespace Icinga\Module\Businessprocess\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\HostNode;

class CheckCommand extends Command
{
    protected $storage;

    protected $hostColors = array(
        0 => array('black', 'lightgreen'),
        1 => array('black', 'lightred'),
        2 => array('black', 'brown'),
        99 => array('black', 'lightgray'),
    );

    protected $serviceColors = array(
        0 => array('black', 'lightgreen'),
        1 => array('black', 'yellow'),
        2 => array('black', 'lightred'),
        3 => array('black', 'lightpurple'),
        99 => array('black', 'lightgray'),
    );

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
        if ($this->params->shift('details')) {
            echo $this->renderProblemTree($node->getProblemTree(), $this->params->shift('colors'));
        }

        exit($node->getState());
    }

    protected function renderProblemTree($tree, $useColors = false, $depth = 0)
    {
        $output = '';

        foreach ($tree as $name => $subtree) {
            $node = $subtree['node'];

            if ($node instanceof HostNode) {
                $colors = $this->hostColors[$node->getState()];
            } else {
                $colors = $this->serviceColors[$node->getState()];
            }

            $state = sprintf('[%s]', $node->getStateName());
            if ($useColors) {
                $state = $this->screen->colorize($state, $colors[0], $colors[1]);
            }

            $output .= sprintf(
                "%s%s %s %s\n",
                str_repeat('  ', $depth),
                $node instanceof BpNode ? $node->getOperator() : '-',
                $state,
                $node->getAlias()
            );
            $output .= $this->renderProblemTree($subtree['children'], $useColors, $depth + 1);
        }

        $output = str_replace("|", "¦", $output);

        return $output;
    }

    protected function getFirstProcessName()
    {
        $list = $this->storage->listProcesses();
        return key($list);
    }
}
