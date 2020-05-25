<?php

namespace Icinga\Module\Businessprocess\State;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\IcingaDbBackend;
use Icinga\Module\Businessprocess\ServiceNode;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;

class IcingaDbState extends IcingaDbBackend
{
    /** @var BpConfig */
    protected $config;

    /** @var IcingadbDatabase */
    protected $backend;

    public function __construct(BpConfig $config)
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

        Benchmark::measure(sprintf(
            'Retrieving states for business process %s using Icinga DB backend',
            $config->getName()
        ));

        $hosts = $config->listInvolvedHostNames();
        if (empty($hosts)) {
            return $this;
        }

        $queryHost = Host::on($this->backend)->with('state');

        $queryHost->getSelectBase()
            ->where(['host.name IN (?)' => $hosts]);

        IcingaDbBackend::applyMonitoringRestriction($queryHost);

        if ($this->config->usesHardStates()) {
            $stateCol = 'state.hard_state';
        } else {
            $stateCol = 'state.soft_state';
        }

        $hostStatusCols = [
            'hostname'          => 'host.name',
            'last_state_change' => 'state.last_state_change',
            'in_downtime'       => 'state.in_downtime',
            'ack'               => 'state.is_acknowledged',
            'state'             => $stateCol,
            'display_name'      =>'host.display_name'
        ];

        $queryHost = $queryHost->columns($hostStatusCols)->assembleSelect();

        $hostStatus = $this->backend->select($queryHost)->fetchAll();

        Benchmark::measure('Retrieved states for ' . count($hostStatus) . ' hosts in ' . $config->getName());

        $queryService = Service::on($this->backend)->with([
            'state',
            'host',
            'host.state'
        ]);
        $queryService->getSelectBase()
            ->where(['service_host.name IN (?)' => $hosts]);

        IcingaDbBackend::applyMonitoringRestriction($queryService);

        $serviceStatusCols = [
            'hostname'          => 'host.name',
            'service'           => 'service.name',
            'last_state_change' => 'state.last_state_change',
            'in_downtime'       => 'state.in_downtime',
            'ack'               => 'host.state.is_acknowledged',
            'state'             => $stateCol,
            'display_name'      => 'service.display_name',
            'host_display_name' => 'host.display_name'
        ];

        $queryService = $queryService->columns($serviceStatusCols)->assembleSelect();

        $serviceStatus = $this->backend->select($queryService)->fetchAll();

        Benchmark::measure('Retrieved states for ' . count($serviceStatus) . ' services in ' . $config->getName());

        $configs = $config->listInvolvedConfigs();
        $hostStatus = (object) $hostStatus;
        $serviceStatus = (object) $serviceStatus;

        foreach ($configs as $cfg) {
            foreach ($serviceStatus as $row) {
                $this->handleDbRow($row, $cfg);
            }
            foreach ($hostStatus as $row) {
                $this->handleDbRow($row, $cfg);
            }
        }

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

        // Since we are fetching the values directly using assembleSelect instead of using ORM,
        // the following changes for 'last_state_change', 'in_downtime' and 'ack' is required
        $node = $config->getNode($key);

        if ($row->state !== null) {
            $node->setState($row->state)->setMissing(false);
        }
        if ($row->last_state_change !== null) {
            $node->setLastStateChange($row->last_state_change/1000);
        }
        if ($row->in_downtime === 'y') {
            $node->setDowntime(true);
        }
        if ($row->ack !== 'n') {
            $node->setAck(true);
        }

        $node->setAlias($row->display_name);

        if ($node instanceof ServiceNode) {
            $node->setHostAlias($row->host_display_name);
        }
    }
}
