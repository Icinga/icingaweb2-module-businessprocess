<?php
/* Icinga Web 2 | (c) 2015 Icinga Development Team | GPLv2+ */

namespace Icinga\Module\Businessprocess\Ido;

use Icinga\Exception\InvalidPropertyException;
use Icinga\Module\Monitoring\Backend\Ido\Query\HostgroupsummaryQuery;
use Zend_Db_Expr;
use Zend_Db_Select;

/**
 * Query for host group summary
 */
class BpHostgroupsummaryQuery extends HostgroupsummaryQuery
{
    protected $stateTypeMap = array(
        'hard' => 'hard_state',
        'soft' => 'state',
    );

    protected $stateType = 'soft';

    public function setStateType($type)
    {
        if (! array_key_exists($type, $this->stateTypeMap)) {
            throw new InvalidPropertyException(
                'type must be one of: ' . join(', ', array_keys($this->stateTypeMap))
            );
        }
        $this->stateType = $type;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function joinBaseTables()
    {
        $stateTypeSuffix = $this->stateTypeMap[$this->stateType];

        $hosts = $this->createSubQuery(
            'Hoststatus',
            array(
                'handled'       => 'host_handled',
                'host_state'    => new Zend_Db_Expr('NULL'),
                'hostgroup_alias',
                'hostgroup_name',
                'object_type',
                'severity'      => 'host_severity',
                'state'         => 'host_' . $stateTypeSuffix,
                'state_change'  => 'host_last_' . $stateTypeSuffix . '_change'
            )
        );
        $hosts->select()->where('hgo.name1 IS NOT NULL'); // TODO(9458): Should be possible using our filters!
        $this->subQueries[] = $hosts;
        $services = $this->createSubQuery(
            'Servicestatus',
            array(
                'handled'       => 'service_handled',
                'host_state'    => 'host_hard_state', // TODO: why?
                'hostgroup_alias',
                'hostgroup_name',
                'object_type',
                'severity'      => new Zend_Db_Expr('NULL'),
                'state'         => 'service_' . $stateTypeSuffix,
                'state_change'  => 'service_last_' . $stateTypeSuffix . '_change'
            )
        );
        $services->select()->where('hgo.name1 IS NOT NULL'); // TODO(9458): Should be possible using our filters!
        $this->subQueries[] = $services;
        $this->summaryQuery = $this->db->select()->union(array($hosts, $services), Zend_Db_Select::SQL_UNION_ALL);
        $this->select->from(array('statussummary' => $this->summaryQuery), array());
        $this->group(array('statussummary.hostgroup_name', 'statussummary.hostgroup_alias'));
        $this->joinedVirtualTables['hoststatussummary'] = true;
    }
}
