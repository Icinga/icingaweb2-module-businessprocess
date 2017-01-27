<?php

namespace Icinga\Module\Businessprocess\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\HostNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class ProcessCommand extends Command
{
    /**
     * @var LegacyStorage
     */
    protected $storage;

    protected $hostColors = array(
        0 => array('black', 'lightgreen'),
        1 => array('lightgray', 'lightred'),
        2 => array('black', 'brown'),
        99 => array('black', 'lightgray'),
    );

    protected $serviceColors = array(
        0 => array('black', 'lightgreen'),
        1 => array('black', 'yellow'),
        2 => array('lightgray', 'lightred'),
        3 => array('black', 'lightpurple'),
        99 => array('black', 'lightgray'),
    );

    public function init()
    {
        $this->storage = new LegacyStorage($this->Config()->getSection('global'));
    }

    /**
     * List all available Business Process Configurations
     *
     * ...or their BusinessProcess Nodes in case a Configuration name is given
     *
     * USAGE
     *
     * icingacli businessprocess list processes [<config-name>] [options]
     *
     * OPTIONS
     *
     *   <config-name>
     *   --no-title    Show only names and no related title
     */
    public function listAction()
    {
        if ($config = $this->params->shift()) {
            $this->listBpNames($this->storage->loadProcess($config));
        } else {
            $this->listConfigNames(! (bool) $this->params->shift('no-title'));
        }
    }

    /**
     * Check a specific process
     *
     * USAGE
     *
     * icingacli businessprocess process check <process> [options]
     *
     * OPTIONS
     *
     *   --config <configname>   Name of the config that contains <process>
     *   --details               Show problem details as a tree
     *   --colors                Show colored output
     *   --state-type <type>     Define which state type to look at. Could be
     *                           either soft or hard, overrides an eventually
     *                           configured default
     */
    public function checkAction()
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

        /** @var BpNode $node */
        $node = $bp->getNode($this->params->shift());
        MonitoringState::apply($bp);
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

    protected function listConfigNames($withTitle)
    {
        foreach ($this->storage->listProcesses() as $key => $title) {
            if ($withTitle) {
                echo $title . "\n";
            } else {
                echo $key . "\n";
            }
        }
    }

    protected function listBpNames(BpConfig $config)
    {
        foreach ($config->listBpNodes() as $title) {
            echo $title . "\n";
        }
    }

    protected function renderProblemTree($tree, $useColors = false, $depth = 0)
    {
        $output = '';

        foreach ($tree as $name => $subtree) {
            /** @var Node $node */
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

        return $output;
    }

    protected function getFirstProcessName()
    {
        $list = $this->storage->listProcessNames();
        return key($list);
    }
}
