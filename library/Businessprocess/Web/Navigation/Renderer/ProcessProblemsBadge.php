<?php

namespace Icinga\Module\Businessprocess\Web\Navigation\Renderer;

use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Web\Navigation\Renderer\BadgeNavigationItemRenderer;

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
            $storage = LegacyStorage::getInstance();
            $count = 0;
            $bp = $storage->loadProcess($this->getBpConfigName());


            foreach ($bp->getRootNodes() as $rootNode) {
                if (! $rootNode->isEmpty() &&
                    $rootNode->getState() !== $rootNode::ICINGA_PENDING
                    && $rootNode->hasProblems()) {
                    $count++;
                }
            }

            $this->count = $count;
            $this->setState(self::STATE_CRITICAL);
        }

        if ($count) {
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
