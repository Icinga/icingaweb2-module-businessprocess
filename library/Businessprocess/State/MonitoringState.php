<?php

namespace Icinga\Module\Businessprocess\State;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Data\Filter\Filter;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\ServiceNode;
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

        $hostFilter = Filter::expression('host_name', '=', $hosts);

        $hostStatus = $backend->select()->from('hostStatus', array(
            'hostname'          => 'host_name',
            'last_state_change' => $hostStateChangeColumn,
            'in_downtime'       => 'host_in_downtime',
            'ack'               => 'host_acknowledged',
            'state'             => $hostStateColumn,
            'display_name'      => 'host_display_name'
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
            'state'             => $serviceStateColumn,
            'display_name'      => 'service_display_name',
            'host_display_name' => 'host_display_name'
        ))->applyFilter($hostFilter)->getQuery()->fetchAll();

        Benchmark::measure('Retrieved states for ' . count($serviceStatus) . ' services in ' . $config->getName());

        $configs = $config->listInvolvedConfigs();
        foreach ($configs as $cfg) {
            foreach ($serviceStatus as $row) {
                $this->handleDbRow($row, $cfg);
            }
            foreach ($hostStatus as $row) {
                $this->handleDbRow($row, $cfg);
            }
        }

        // TODO: Union, single query?
        Benchmark::measure('Got states for business process ' . $config->getName());

        return $this;
    }

    protected function handleDbRow($row, BpConfig $config)
    {
        $key = BpConfig::joinNodeName(
            $row->hostname,
            property_exists($row, 'service')
                ? $row->service
                : 'Hoststatus'
        );

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

        $node->setAlias($row->display_name);

        if ($node instanceof ServiceNode) {
            $node->setHostAlias($row->host_display_name);
        }
    }
}
