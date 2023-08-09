<?php

namespace Icinga\Module\Businessprocess\State;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Businessprocess\ServiceNode;
use Icinga\Module\Icingadb\Common\IcingaRedis;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Sql\Connection as IcingaDbConnection;
use ipl\Stdlib\Filter;

class IcingaDbState
{
    /** @var BpConfig */
    protected $config;

    /** @var IcingaDbConnection */
    protected $backend;

    public function __construct(BpConfig $config)
    {
        $this->config = $config;
        $this->backend = IcingaDbObject::fetchDb();
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

        $involvedHostNames = $config->listInvolvedHostNames();
        if (empty($involvedHostNames)) {
            return $this;
        }

        Benchmark::measure(sprintf(
            'Retrieving states for business process %s using Icinga DB backend',
            $config->getName()
        ));

        $hosts = Host::on($this->backend)->columns([
            'id' => 'host.id',
            'name' => 'host.name',
            'display_name' => 'host.display_name',
            'hard_state' => 'host.state.hard_state',
            'soft_state' => 'host.state.soft_state',
            'last_state_change' => 'host.state.last_state_change',
            'in_downtime' => 'host.state.in_downtime',
            'is_acknowledged' => 'host.state.is_acknowledged'
        ])->filter(Filter::equal('host.name', $involvedHostNames));

        $services = Service::on($this->backend)->columns([
            'id' => 'service.id',
            'name' => 'service.name',
            'display_name' => 'service.display_name',
            'host_name' => 'host.name',
            'host_display_name' => 'host.display_name',
            'hard_state' => 'service.state.hard_state',
            'soft_state' => 'service.state.soft_state',
            'last_state_change' => 'service.state.last_state_change',
            'in_downtime' => 'service.state.in_downtime',
            'is_acknowledged' => 'service.state.is_acknowledged'
        ])->filter(Filter::equal('host.name', $involvedHostNames));

        // All of this is ipl-sql now, for performance reasons
        foreach ($config->listInvolvedConfigs() as $cfg) {
            $serviceIds = [];
            $serviceResults = [];
            foreach ($this->backend->yieldAll($services->assembleSelect()) as $row) {
                $row->hex_id = bin2hex($row->id);
                $serviceIds[] = $row->hex_id;
                $serviceResults[] = $row;
            }

            $redisServiceResults = iterator_to_array(IcingaRedis::fetchServiceState($serviceIds, [
                'hard_state',
                'soft_state',
                'last_state_change',
                'in_downtime',
                'is_acknowledged'
            ]));
            foreach ($serviceResults as $row) {
                if (isset($redisServiceResults[$row->hex_id])) {
                    $row = (object) array_merge(
                        (array) $row,
                        $redisServiceResults[$row->hex_id]
                    );
                }

                $this->handleDbRow($row, $cfg, 'service');
            }

            Benchmark::measure('Retrieved states for ' . count($serviceIds) .  ' services in ' . $config->getName());

            $hostIds = [];
            $hostResults = [];
            foreach ($this->backend->yieldAll($hosts->assembleSelect()) as $row) {
                $row->hex_id = bin2hex($row->id);
                $hostIds[] = $row->hex_id;
                $hostResults[] = $row;
            }

            $redisHostResults = iterator_to_array(IcingaRedis::fetchHostState($hostIds, [
                'hard_state',
                'soft_state',
                'last_state_change',
                'in_downtime',
                'is_acknowledged'
            ]));
            foreach ($hostResults as $row) {
                if (isset($redisHostResults[$row->hex_id])) {
                    $row = (object) array_merge(
                        (array) $row,
                        $redisHostResults[$row->hex_id]
                    );
                }

                $this->handleDbRow($row, $cfg, 'host');
            }

            Benchmark::measure('Retrieved states for ' . count($hostIds) .  ' hosts in ' . $config->getName());
        }

        Benchmark::measure('Got states for business process ' . $config->getName());

        return $this;
    }

    protected function handleDbRow($row, BpConfig $config, $type)
    {
        if ($type === 'service') {
            $key = BpConfig::joinNodeName($row->host_name, $row->name);
        } else {
            $key = BpConfig::joinNodeName($row->name, 'Hoststatus');
        }

        // We fetch more states than we need, so skip unknown ones
        if (! $config->hasNode($key)) {
            return;
        }

        $node = $config->getNode($key);

        if ($this->config->usesHardStates()) {
            if ($row->hard_state !== null) {
                $node->setState($row->hard_state)->setMissing(false);
            }
        } else {
            if ($row->soft_state !== null) {
                $node->setState($row->soft_state)->setMissing(false);
            }
        }

        if ($row->last_state_change !== null) {
            $node->setLastStateChange($row->last_state_change / 1000.0);
        }

        $node->setDowntime($row->in_downtime === 'y');
        $node->setAck($row->is_acknowledged === 'y');
        $node->setAlias($row->display_name);

        if ($node instanceof ServiceNode) {
            $node->setHostAlias($row->host_display_name);
        }
    }
}
