<?php

namespace Icinga\Module\Businessprocess\State;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\HostGroupnode;
use Icinga\Module\Businessprocess\Ido\BpHostgroupsummaryQuery;
use Icinga\Module\Businessprocess\Ido\HostgroupsummaryQuery;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;

class MonitoringState
{
    /** @var BpConfig */
    protected $config;

    /** @var MonitoringBackend */
    protected $backend;

    private function __construct(BpConfig $config)
    {
        $this->config = $config;
        $this->backend = $config->getBackend();
    }

    public static function apply(BpConfig $config)
    {
        $self = new static($config);
        $self->retrieveStatesFromBackend();
        return $config;
    }

    public function retrieveStatesFromBackend()
    {
        $config = $this->config;

        try {
            $this->reallyRetrieveStatesFromBackend();
        } catch (Exception $e) {
            $config->addError(
                $config->translate('Could not retrieve process state: %s'),
                $e->getMessage()
            );
        }
    }

    public function reallyRetrieveStatesFromBackend()
    {
        $config = $this->config;

        Benchmark::measure('Retrieving states for business process ' . $config->getName());
        $backend = $this->backend;

        if ($config->usesHardStates()) {
            $hostStateColumn          = 'host_hard_state';
            $hostStateChangeColumn    = 'host_last_hard_state_change';
            $serviceStateColumn       = 'service_hard_state';
            $serviceStateChangeColumn = 'service_last_hard_state_change';
        } else {
            $hostStateColumn          = 'host_state';
            $hostStateChangeColumn    = 'host_last_state_change';
            $serviceStateColumn       = 'service_state';
            $serviceStateChangeColumn = 'service_last_state_change';
        }

        $hosts = $config->listInvolvedHostNames();
        if (empty($hosts)) {
            return $this;
        }

        $hostFilter = Filter::expression('host', '=', $hosts);

        $hostStatus = $backend->select()->from('hostStatus', array(
            'hostname'          => 'host_name',
            'last_state_change' => $hostStateChangeColumn,
            'in_downtime'       => 'host_in_downtime',
            'ack'               => 'host_acknowledged',
            'state'             => $hostStateColumn
        ))->applyFilter($hostFilter)->getQuery()->fetchAll();

        Benchmark::measure('Retrieved states for ' . count($hostStatus) . ' hosts in ' . $config->getName());

        // NOTE: we intentionally filter by host_name ONLY
        // Tests with host IN ... AND service IN shows longer query times
        // while retrieving 1635 (in 5ms) vs. 1388 (in ~430ms) services
        $serviceStatus = $backend->select()->from('serviceStatus', array(
            'hostname'          => 'host_name',
            'service'           => 'service_description',
            'last_state_change' => $serviceStateChangeColumn,
            'in_downtime'       => 'service_in_downtime',
            'ack'               => 'service_acknowledged',
            'state'             => $serviceStateColumn
        ))->applyFilter($hostFilter)->getQuery()->fetchAll();

        Benchmark::measure('Retrieved states for ' . count($serviceStatus) . ' services in ' . $config->getName());

        if (! empty($hostgroups = $config->listInvolvedHostGroups())) {
            $hostgroupFilter = Filter::expression('hostgroup_name', '=', $hostgroups);
            $hostgroupSummary = new BpHostgroupsummaryQuery($backend->getResource(), array(
                'hostgroup_alias',
                'hostgroup_name',
                'hosts_down_handled',
                'hosts_down_handled_last_state_change',
                'hosts_down_unhandled',
                'hosts_down_unhandled_last_state_change',
                'hosts_pending',
                'hosts_pending_last_state_change',
                'hosts_total',
                'hosts_unreachable_handled',
                'hosts_unreachable_handled_last_state_change',
                'hosts_unreachable_unhandled',
                'hosts_unreachable_unhandled_last_state_change',
                'hosts_up',
                'hosts_up_last_state_change',
                'services_critical_handled',
                'services_critical_unhandled',
                'services_ok',
                'services_pending',
                'services_total',
                'services_unknown_handled',
                'services_unknown_unhandled',
                'services_warning_handled',
                'services_warning_unhandled'
            ));
            if ($config->usesHardStates()) {
                $hostgroupSummary->setStateType('hard');
            }
            $hostgroupStatus = $hostgroupSummary->applyFilter($hostgroupFilter)->fetchAll();

            Benchmark::measure('Retrieved states for ' . count($hostgroupStatus) . ' hostgroups in ' . $config->getName());
        } else {
            $hostgroupStatus = array();
        }

        foreach ($serviceStatus as $row) {
            $this->handleDbRow($row, $config);
        }

        foreach ($hostStatus as $row) {
            $this->handleDbRow($row, $config);
        }

        foreach ($hostgroupStatus as $row) {
            $this->handleHostgroupRow($row, $config);
        }

        // TODO: Union, single query?
        Benchmark::measure('Got states for business process ' . $config->getName());

        return $this;
    }

    protected function handleDbRow($row, BpConfig $config)
    {
        $key = $row->hostname;
        if (property_exists($row, 'service')) {
            $key .= ';' . $row->service;
        } else {
            $key .= ';Hoststatus';
        }

        // We fetch more states than we need, so skip unknown ones
        if (! $config->hasNode($key)) {
            return;
        }

        $node = $config->getNode($key);

        if ($row->state !== null) {
            $node->setState($row->state)->setMissing(false);
        }
        if ($row->last_state_change !== null) {
            $node->setLastStateChange($row->last_state_change);
        }
        if ((int) $row->in_downtime === 1) {
            $node->setDowntime(true);
        }
        if ((int) $row->ack === 1) {
            $node->setAck(true);
        }
    }

    protected function handleHostgroupRow($row, BpConfig $config)
    {
        $key = 'HOSTGROUP;' . $row->hostgroup_name;

        if (! $config->hasNode($key)) {
            $config->addError('Could not add hostgroup status for %s', $key);
            return;
        }

        /** @var HostgroupNode $node */
        $node = $config->getNode($key);

        // TODO: all special TLV handlings?
        // TODO: badges

        if ($row->hosts_down_unhandled > 0 || $row->services_critical_unhandled) {
            $node->setState(2);
        } elseif ($row->hosts_down_handled > 0 || $row->services_critical_handled) {
            $node->setState(2);
            $node->setAck(true);
        } elseif ($row->services_warning_unhandled > 0) {
            $node->setState(1);
        } elseif ($row->services_warning_handled > 0) {
            $node->setState(1);
            $node->setAck(true);
        } else {
            $node->setState(0);
        }

        $node->setMissing(false);

        // TODO: last change

        $node->setCounters(array(
            'OK'          => $row->services_ok,
            'WARNING'     => $row->services_warning_handled + $row->services_warning_unhandled,
            'CRITICAL'    => $row->services_critical_handled + $row->services_critical_unhandled,
            'UNKNOWN'     => $row->services_unknown_handled + $row->services_unknown_unhandled,
            'PENDING'     => $row->services_pending + $row->hosts_pending,
            'UP'          => $row->hosts_up,
            'DOWN'        => $row->hosts_down_handled + $row->hosts_down_unhandled,
            'UNREACHABLE' => $row->hosts_unreachable_handled + $row->hosts_unreachable_unhandled,
            'MISSING'     => 0,
        ));
    }
}
