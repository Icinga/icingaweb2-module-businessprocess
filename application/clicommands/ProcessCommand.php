<?php

namespace Icinga\Module\Businessprocess\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Application\Modules\Module;
use Icinga\Cli\Command;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\HostNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Businessprocess\State\IcingaDbState;
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
        $this->storage = LegacyStorage::getInstance();
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
     *   --blame                 Show problem details as a tree reduced to the
     *                           nodes which have the same state as the business
     *                           process
     *   --root-cause            Used in combination with --blame. Only shows
     *                           the path of the nodes which are responsible for
     *                           the state of the business process
     *   --downtime-is-ok        Treat hosts/services in downtime always as
     *                           UP/OK.
     *   --ack-is-ok             Treat acknowledged hosts/services always as
     *                           UP/OK.
     */
    public function checkAction()
    {
        $nodeName = $this->params->shift();
        if (! $nodeName) {
            Logger::error('A process name is required');
            exit(1);
        }

        try {
            $name = $this->params->get('config');
            if ($name === null) {
                $name = $this->getFirstProcessName();
            }

            $bp = $this->storage->loadProcess($name);
        } catch (Exception $err) {
            Logger::error("Can't access configuration '%s': %s", $name, $err->getMessage());

            exit(3);
        }

        if (null !== ($stateType = $this->params->get('state-type'))) {
            if ($stateType === 'soft') {
                $bp->useSoftStates();
            }
            if ($stateType === 'hard') {
                $bp->useHardStates();
            }
        }

        try {
            /** @var BpNode $node */
            $node = $bp->getNode($nodeName);
            if (Module::exists('icingadb')
                && (! $bp->hasBackendName() && IcingadbSupport::useIcingaDbAsBackend())
            ) {
                IcingaDbState::apply($bp);
            } else {
                MonitoringState::apply($bp);
            }

            if ($bp->hasErrors()) {
                Logger::error("Checking Business Process '%s' failed: %s\n", $name, $bp->getErrors());

                exit(3);
            }
        } catch (Exception $err) {
            Logger::error("Checking Business Process '%s' failed: %s", $name, $err);

            exit(3);
        }

        if ($this->params->shift('ack-is-ok')) {
            Node::setAckIsOk();
        }

        if ($this->params->shift('downtime-is-ok')) {
            Node::setDowntimeIsOk();
        }

        printf("Business Process %s: %s\n", $node->getStateName(), $node->getAlias());
        if ($this->params->shift('details')) {
            echo $this->renderProblemTree($node->getProblemTree(), $this->params->shift('colors'));
        }
        if ($this->params->shift('blame')) {
            echo $this->renderProblemTree(
                $node->getProblemTreeBlame($this->params->shift('root-cause')),
                $this->params->shift('colors')
            );
        }

        exit($node->getState());
    }

    protected function listBpNames(BpConfig $config)
    {
        foreach ($config->listBpNodes() as $title) {
            echo $title . "\n";
        }
    }

    protected function renderProblemTree($tree, $useColors = false, $depth = 0, BpNode $parent = null)
    {
        $output = '';

        foreach ($tree as $name => $subtree) {
            /** @var Node $node */
            $node = $subtree['node'];
            $state = $parent !== null ? $parent->getChildState($node) : $node->getState();

            if ($node instanceof HostNode) {
                $colors = $this->hostColors[$state];
            } else {
                $colors = $this->serviceColors[$state];
            }

            $state = sprintf('[%s]', $node->getStateName($state));
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

            if ($node instanceof BpNode) {
                $output .= $this->renderProblemTree($subtree['children'], $useColors, $depth + 1, $node);
            }
        }

        return $output;
    }

    protected function getFirstProcessName()
    {
        $list = $this->storage->listProcessNames();
        return key($list);
    }
}
