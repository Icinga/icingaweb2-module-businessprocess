<?php
// Icinga Business Process Modelling | (c) 2021 Icinga GmbH | GPLv2

namespace Icinga\Module\Businessprocess;

use InvalidArgumentException;
use ipl\Stdlib\Str;

trait Sort
{
    /** @var callable Actual sorting function */
    protected $sortFn;

    /**
     * Set the sort specification
     *
     * @param string $sort
     *
     * @return $this
     *
     * @throws InvalidArgumentException When sorting according to the specified specification is not possible
     */
    public function setSort($sort)
    {
        list($sortBy, $direction) = Str::symmetricSplit($sort, ' ', 2, 'asc');

        switch ($sortBy) {
            case 'display_name':
                if ($direction === 'asc') {
                    $this->sortFn = function (array &$nodes) {
                        ksort($nodes, SORT_NATURAL);
                    };
                } else {
                    $this->sortFn = function (array &$nodes) {
                        krsort($nodes, SORT_NATURAL);
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
                    "Can't sort by %s. It's only possible to sort by display_name or state",
                    $sortBy
                ));
        }

        return $this;
    }

    /**
     * Sort the given array as specified by {@link setSort()}
     *
     * @param array $subject
     *
     * @return array
     */
    public function sort(array $subject)
    {
        if (is_callable($this->sortFn)) {
            call_user_func_array($this->sortFn, [&$subject]);
        }

        return $subject;
    }
}
