<?php
// Icinga Business Process Modelling | (c) 2023 Icinga GmbH | GPLv2

namespace Icinga\Module\Businessprocess\Common;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Node;
use InvalidArgumentException;
use ipl\Stdlib\Str;

trait Sort
{
    /** @var ?string Current sort specification */
    protected $sort;

    /** @var ?callable Actual sorting function */
    protected $sortFn;

    /**
     * Get the sort specification
     *
     * @return ?string
     */
    public function getSort(): ?string
    {
        return $this->sort;
    }

    /**
     * Set the sort specification
     *
     * @param ?string $sort
     *
     * @return $this
     *
     * @throws InvalidArgumentException When sorting according to the specified specification is not possible
     */
    public function setSort(?string $sort): self
    {
        if (empty($sort)) {
            return $this;
        }

        list($sortBy, $direction) = Str::symmetricSplit($sort, ' ', 2, 'asc');

        switch ($sortBy) {
            case 'manual':
                if ($direction === 'asc') {
                    $this->sortFn = function (array &$nodes) {
                        $firstNode = reset($nodes);
                        if ($firstNode instanceof BpNode && $firstNode->getDisplay() > 0) {
                            $nodes = self::applyManualSorting($nodes);
                        }

                        // Child nodes don't need to be ordered in this case, their implicit order is significant
                    };
                } else {
                    $this->sortFn = function (array &$nodes) {
                        $firstNode = reset($nodes);
                        if ($firstNode instanceof BpNode && $firstNode->getDisplay() > 0) {
                            uasort($nodes, function (BpNode $a, BpNode $b) {
                                return $b->getDisplay() <=> $a->getDisplay();
                            });
                        } else {
                            $nodes = array_reverse($nodes);
                        }
                    };
                }

                break;
            case 'display_name':
                if ($direction === 'asc') {
                    $this->sortFn = function (array &$nodes) {
                        uasort($nodes, function (Node $a, Node $b) {
                            return strnatcasecmp(
                                $a->getAlias() ?? $a->getName(),
                                $b->getAlias() ?? $b->getName()
                            );
                        });
                    };
                } else {
                    $this->sortFn = function (array &$nodes) {
                        uasort($nodes, function (Node $a, Node $b) {
                            return strnatcasecmp(
                                $b->getAlias() ?? $b->getName(),
                                $a->getAlias() ?? $a->getName()
                            );
                        });
                    };
                }

                break;
            case 'state':
                if ($direction === 'asc') {
                    $this->sortFn = function (array &$nodes) {
                        uasort($nodes, function (Node $a, Node $b) {
                            return $a->getSortingState() <=> $b->getSortingState();
                        });
                    };
                } else {
                    $this->sortFn = function (array &$nodes) {
                        uasort($nodes, function (Node $a, Node $b) {
                            return $b->getSortingState() <=> $a->getSortingState();
                        });
                    };
                }

                break;
            default:
                throw new InvalidArgumentException(sprintf(
                    "Can't sort by %s. It's only possible to sort by manual order, display_name or state",
                    $sortBy
                ));
        }

        $this->sort = $sort;

        return $this;
    }

    /**
     * Sort the given nodes as specified by {@see setSort()}
     *
     * If {@see setSort()} has not been called yet, the default sort specification is used
     *
     * @param array $nodes
     *
     * @return array
     */
    public function sort(array $nodes): array
    {
        if (empty($nodes)) {
            return $nodes;
        }

        if ($this->sortFn !== null) {
            call_user_func_array($this->sortFn, [&$nodes]);
        }

        return $nodes;
    }

    /**
     * Apply manual sort order on the given process nodes
     *
     * @param array $bpNodes
     *
     * @return array
     */
    public static function applyManualSorting(array $bpNodes): array
    {
        uasort($bpNodes, function (BpNode $a, BpNode $b) {
            return $a->getDisplay() <=> $b->getDisplay();
        });

        return $bpNodes;
    }
}
