<?php

namespace Icinga\Module\Businessprocess\State;

use Exception;
use Icinga\Application\Benchmark;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\IcingaDbObject;
use Icinga\Module\Businessprocess\ServiceNode;
use Icinga\Module\Icingadb\Common\Auth;
use Icinga\Module\Icingadb\Model\Host;
use Icinga\Module\Icingadb\Model\Service;

class IcingaDbState
{
    use Auth;

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
        IcingaDbObject::applyIcingaDbRestrictions($queryHost);

        $queryHost->getSelectBase()
            ->where(['host.name IN (?)' => $hosts]);

        $hostObject = $queryHost->getModel()->getTableName();

        IcingaDbObject::applyIcingaDbRestrictions($queryHost);

        Benchmark::measure('Retrieved states for ' . $queryHost->count() . ' hosts in ' . $config->getName());

        $queryService = Service::on($this->backend)->with([
            'state',
            'host',
            'host.state'
        ]);

        $queryService->getSelectBase()
            ->where(['service_host.name IN (?)' => $hosts]);

        IcingaDbObject::applyIcingaDbRestrictions($queryService);

        Benchmark::measure('Retrieved states for ' . $queryService->count() . ' services in ' . $config->getName());

        $configs = $config->listInvolvedConfigs();

        $serviceObject = $queryService->getModel()->getTableName();

        foreach ($configs as $cfg) {
            foreach ($queryService as $row) {
                $this->handleDbRow($row, $cfg, $serviceObject);
            }
            foreach ($queryHost as $row) {
                $this->handleDbRow($row, $cfg, $hostObject);
            }
        }

        Benchmark::measure('Got states for business process ' . $config->getName());

        return $this;
    }

    protected function handleDbRow($row, BpConfig $config, $objectName)
    {
        if ($objectName === 'service') {
            $key = $row->host->name . ';' . $row->name;
        } else {
            $key = $row->name . ';Hoststatus';
        }

        // We fetch more states than we need, so skip unknown ones
        if (! $config->hasNode($key)) {
            return;
        }

        $node = $config->getNode($key);

        if ($this->config->usesHardStates()) {
            if ($row->state->hard_state !== null) {
                $node->setState($row->state->hard_state)->setMissing(false);
            }
        } else {
            if ($row->state->soft_state !== null) {
                $node->setState($row->state->soft_state)->setMissing(false);
            }
        }

        if ($row->state->last_state_change !== null) {
            $node->setLastStateChange($row->state->last_state_change/1000);
        }
        if ($row->state->in_downtime === 'y') {
            $node->setDowntime(true);
        }
        if ($row->state->is_acknowledged !== 'n') {
            $node->setAck(true);
        }

        $node->setAlias($row->display_name);

        if ($node instanceof ServiceNode) {
            $node->setHostAlias($row->host->display_name);
        }
    }
}
