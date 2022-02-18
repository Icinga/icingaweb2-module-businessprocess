<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Authentication\Auth;
use Icinga\Data\Filter\Filter;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;

class MonitoringRestrictions
{
    /**
     * Return a filter for the given restriction
     *
     * @param   string $name        Name of the restriction
     *
     * @return  Filter|null         Filter object or null if the authenticated user is not restricted
     * @throws  ConfigurationError  If the restriction contains invalid filter columns
     */
    public static function getRestriction($name)
    {
        // Borrowed from Icinga\Module\Monitoring\Controller
        $restriction = Filter::matchAny();
        $restriction->setAllowedFilterColumns(array(
            'host_name',
            'hostgroup_name',
            'instance_name',
            'service_description',
            'servicegroup_name',
            function ($c) {
                return preg_match('/^_(?:host|service)_/i', $c);
            }
        ));

        foreach (Auth::getInstance()->getRestrictions($name) as $filter) {
            if ($filter === '*') {
                return Filter::matchAny();
            }

            try {
                $restriction->addFilter(Filter::fromQueryString($filter));
            } catch (QueryException $e) {
                throw new ConfigurationError(
                    mt(
                        'monitoring',
                        'Cannot apply restriction %s using the filter %s. You can only use the following columns: %s'
                    ),
                    $name,
                    $filter,
                    implode(', ', array(
                        'instance_name',
                        'host_name',
                        'hostgroup_name',
                        'service_description',
                        'servicegroup_name',
                        '_(host|service)_<customvar-name>'
                    )),
                    $e
                );
            }
        }

        return $restriction;
    }
}
