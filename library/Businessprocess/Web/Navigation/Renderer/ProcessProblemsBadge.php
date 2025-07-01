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
     * Icinga state to badge state mapping
     *
     * @var array<int, string>
     */
    public const NODE_STATE_TO_BADGE_STATE = [
        Node::ICINGA_OK => self::STATE_OK,
        Node::ICINGA_WARNING => self::STATE_WARNING,
        Node::ICINGA_CRITICAL => self::STATE_CRITICAL,
        Node::ICINGA_UNKNOWN => self::STATE_UNKNOWN,
        Node::ICINGA_PENDING => self::STATE_PENDING
    ];

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
            $state = Node::ICINGA_OK;

            try {
                $storage = LegacyStorage::getInstance();

                // State is not applied here, because it's already done in ProcessesProblemsBadge.
                // It runs earlier as it is part of the parent menu entry. Do we rely on an implementation detail here?
                // Probably, but it is how it is for a very long time. So it is unlikely to change. (Finger crossed.)
                $bp = $storage->loadProcess($this->getBpConfigName());
                foreach ($bp->getRootNodes() as $rootNode) {
                    $nodeState = $rootNode->getState();
                    if (! $rootNode->isEmpty() &&
                        ! in_array($nodeState, [Node::ICINGA_OK, Node::ICINGA_PENDING], true)
                    ) {
                        if ($nodeState === $state) {
                            $count++;
                        } elseif ($nodeState > $state) {
                            $count = 1;
                            $state = $nodeState;
                        }
                    }
                }
            } catch (Throwable $e) {
                Logger::error('Failed to load business process "%s": %s', $this->getBpConfigName(), $e);
            }

            $this->count = $count;

            $this->setState(self::NODE_STATE_TO_BADGE_STATE[$state] ?? self::STATE_UNKNOWN);
            $this->setTitle(sprintf(
                tp('One unhandled root node', '%d unhandled root nodes', $count),
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
