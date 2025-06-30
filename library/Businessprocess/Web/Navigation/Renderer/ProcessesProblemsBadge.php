<?php

namespace Icinga\Module\Businessprocess\Web\Navigation\Renderer;

use Icinga\Application\Modules\Module;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Web\Navigation\Renderer\BadgeNavigationItemRenderer;

class ProcessesProblemsBadge extends BadgeNavigationItemRenderer
{
    /**
     * Cached count
     *
     * @var int
     */
    protected $count;

    public function getCount()
    {
        if ($this->count === null) {
            $storage = LegacyStorage::getInstance();
            $count = 0;

            foreach ($storage->listProcessNames() as $processName) {
                $bp = $storage->loadProcess($processName);
                if (Module::exists('icingadb') &&
                    (! $bp->hasBackendName() && IcingadbSupport::useIcingaDbAsBackend())
                ) {
                    IcingaDbState::apply($bp);
                } else {
                    MonitoringState::apply($bp);
                }

                foreach ($bp->getRootNodes() as $rootNode) {
                    if (! $rootNode->isEmpty() &&
                        ! in_array($rootNode->getState(), [Node::ICINGA_OK, Node::ICINGA_PENDING], true)) {
                        $count++;
                        break;
                    }
                }
            }

            $this->count = $count;
            $this->setState(self::STATE_CRITICAL);
        }

        return $this->count;
    }
}
