<?php

namespace Icinga\Module\Businessprocess\Clicommands;

use Exception;
use Icinga\Application\Logger;
use Icinga\Application\Modules\Module;
use Icinga\Cli\Command;
use Icinga\Module\Businessprocess\Modification\NodeRemoveAction;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class CleanupCommand extends Command
{
    /**
     * @var LegacyStorage
     */
    protected $storage;

    protected $defaultActionName = 'cleanup';

    public function init()
    {
        $this->storage = LegacyStorage::getInstance();
    }

    /**
     * Cleanup all missing monitoring nodes from the specified config name
     * If no config name is specified, the missing nodes are cleaned from all available configs.
     * Invalid config files and file names are ignored
     *
     * USAGE
     *
     * icingacli businessprocess cleanup [<config-name>]
     *
     * OPTIONS
     *
     *   <config-name>
     */
    public function cleanupAction(): void
    {
        $configNames = (array) $this->params->shift() ?: $this->storage->listAllProcessNames();
        $foundMissingNode = false;
        foreach ($configNames as $configName) {
            if (! $this->storage->hasProcess($configName)) {
                continue;
            }

            try {
                $bp = $this->storage->loadProcess($configName);
            } catch (Exception $e) {
                Logger::error(
                    'Failed to scan the %s.conf file for missing nodes. Faulty config found.',
                    $configName
                );

                continue;
            }

            if (Module::exists('icingadb')
                && (! $bp->hasBackendName() && IcingadbSupport::useIcingaDbAsBackend())
            ) {
                IcingaDbState::apply($bp);
            } else {
                MonitoringState::apply($bp);
            }

            $removedNodes = [];
            foreach (array_keys($bp->getMissingChildren()) as $missingNode) {
                $node = $bp->getNode($missingNode);
                $remove = new NodeRemoveAction($node);

                try {
                    if ($remove->appliesTo($bp)) {
                        $remove->applyTo($bp);
                        $removedNodes[] = $node->getName();
                        $this->storage->storeProcess($bp);
                        $bp->clearAppliedChanges();

                        $foundMissingNode = true;
                    }
                } catch (Exception $e) {
                    Logger::error(sprintf('(%s.conf) %s', $configName, $e->getMessage()));

                    continue;
                }
            }

            if (! empty($removedNodes)) {
                echo sprintf(
                    'Removed following %d missing node(s) from %s.conf successfully:',
                    count($removedNodes),
                    $configName
                );

                echo "\n" . implode("\n", $removedNodes) . "\n\n";
            }
        }

        if (! $foundMissingNode) {
            echo "No missing node found.\n";
        }
    }
}
