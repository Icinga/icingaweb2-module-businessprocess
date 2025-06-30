<?php

namespace Icinga\Module\Businessprocess\Web\Navigation\Renderer;

use Icinga\Module\Businessprocess\Node;
use Icinga\Application\Logger;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Web\Navigation\Renderer\BadgeNavigationItemRenderer;
use Throwable;

class ProcessProblemsBadge extends BadgeNavigationItemRenderer
{
    /**
     * Cached count
     *
     * @var int
     */
    protected $count;

    /** @var string */
    private $bpConfigName;

    public function getCount()
    {
        if ($this->count === null) {
            $count = 0;

            try {
                $storage = LegacyStorage::getInstance();

                // State is not applied here, because it's already done in ProcessesProblemsBadge.
                // It runs earlier as it is part of the parent menu entry. Do we rely on an implementation detail here?
                // Probably, but it is how it is for a very long time. So it is unlikely to change. (Finger crossed.)
                $bp = $storage->loadProcess($this->getBpConfigName());
                foreach ($bp->getRootNodes() as $rootNode) {
                    if (! $rootNode->isEmpty() &&
                        ! in_array($rootNode->getState(), [Node::ICINGA_OK, Node::ICINGA_PENDING], true)) {
                        $count++;
                    }
                }
            } catch (Throwable $e) {
                Logger::error('Failed to load business process "%s": %s', $this->getBpConfigName(), $e);
            }

            $this->count = $count;

            $this->setState(self::STATE_CRITICAL);
            $this->setTitle(sprintf(
                tp('One unhandled root node critical', '%d unhandled root nodes critical', $count),
                $count
            ));
        }

        return $this->count;
    }

    public function setBpConfigName($bpConfigName)
    {
        $this->bpConfigName = $bpConfigName;

        return $this;
    }

    public function getBpConfigName()
    {
        return $this->bpConfigName;
    }
}
