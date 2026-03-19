<?php

// SPDX-FileCopyrightText: 2022 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Web\Navigation\Renderer;

use Icinga\Application\Logger;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Web\Navigation\Renderer\BadgeNavigationItemRenderer;
use Throwable;

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
            $count = 0;
            $state = Node::ICINGA_OK;

            try {
                $storage = LegacyStorage::getInstance();

                foreach ($storage->listProcessNames() as $processName) {
                    $bp = $storage->loadProcess($processName);
                    $bp->applyDbStates();

                    foreach ($bp->getRootNodes() as $rootNode) {
                        $nodeState = $rootNode->getState();
                        if (
                            ! $rootNode->isEmpty() &&
                            ! in_array($nodeState, [Node::ICINGA_OK, Node::ICINGA_PENDING], true)
                        ) {
                            if ($nodeState === $state) {
                                $count++;
                            } elseif ($nodeState > $state) {
                                $count = 1;
                                $state = $nodeState;
                            }

                            break;
                        }
                    }
                }
            } catch (Throwable $e) {
                Logger::error('Failed to load business processes: %s', $e);
            }

            $this->count = $count;
            $this->setState(ProcessProblemsBadge::NODE_STATE_TO_BADGE_STATE[$state] ?? self::STATE_UNKNOWN);
        }

        return $this->count;
    }
}
