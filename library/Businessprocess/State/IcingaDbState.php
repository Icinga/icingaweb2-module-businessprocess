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

ini_set("xdebug.var_display_max_children", -1);
ini_set("xdebug.var_display_max_data", -1);
ini_set("xdebug.var_display_max_depth", -1);

class IcingaDbState extends IcingaDbBackend
{
    /** @var BpConfig */
    protected $config;

    /** @var IcingaDbBackend */
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

        Benchmark::measure('Retrieving states for business process ' . $config->getName());
        $backend = $this->backend;

        $hosts = $config->listInvolvedHostNames();
        if (empty($hosts)) {
            return $this;
        }

        $queryHost = Host::on($backend)->with('state');
        $queryHost->getSelectBase()
            ->where(['host.name IN (?)' => $hosts]);

        $columns = $queryHost->assembleSelect()->getColumns();
        $resetHostCols = [];
        foreach ($columns as $column)
        {
            $tmpKey = str_replace('.','_',$column);
            $resetHostCols[] = $tmpKey;
        }
        $this->applyMonitoringRestriction($queryHost);

//        /** @var Host $host */
        $hostList = $queryHost->assembleSelect();
        $hostList = $backend->select($hostList)->fetchAll();

        foreach ($hostList as $idx => $hst)
        {
            $hst = get_object_vars($hst);
            $hostColVals = array_values($hst);
            $hst = array_combine($resetHostCols, $hostColVals);
            $hostList[$idx] = $hst;
            if($hst['host_state_state_type'] === 'hard') {
                $hostStateCol = 'host_state_hard_state';
            } else {
                $hostStateCol = 'host_state_soft_state';
            }
        }

        $hostStatusCols = array(
            'hostname'          => 'host_name',
            'last_state_change' => 'host_state_last_state_change',
            'in_downtime'       => 'host_state_in_downtime',
            'ack'               => 'host_state_is_acknowledged',
            'state'             => $hostStateCol,
            'display_name'      =>'host_display_name'
        );
        $hostStatus = $this->selectArrayCols($hostList,$hostStatusCols);

        Benchmark::measure('Retrieved states for ' . count($hostStatus) . ' hosts in ' . $config->getName());

        $queryService = Service::on($backend)->with([
            'state',
            'host',
            'host.state'
        ]);
        $queryService->getSelectBase()
            ->where(['service_host.name IN (?)' => $hosts]);

        $columns = $queryService->assembleSelect()->getColumns();
        $resetServiceCols = [];
        foreach ($columns as $column)
        {
            $tmpKey = str_replace('.','_',$column);
            $resetServiceCols[] = $tmpKey;
        }
        $this->applyMonitoringRestriction($queryService);

        $serviceList = $queryService->assembleSelect();

        $serviceList = $backend->select($serviceList)->fetchAll();

        foreach ($serviceList as $idx => $srvc)
        {
            $srvc = get_object_vars($srvc);
            $serviceColVals = array_values($srvc);
            $srvc = array_combine($resetServiceCols, $serviceColVals);
            $serviceList[$idx] = $srvc;
            if($srvc['service_state_state_type'] === 'hard') {
                $serviceStateCol = 'service_state_hard_state';
            } else {
                $serviceStateCol = 'service_state_soft_state';
            }
        }

        $serviceStatusCols = array(
            'hostname'          => 'service_host_name',
            'service'           => 'service_name',
            'last_state_change' => 'service_state_last_state_change',
            'in_downtime'       => 'service_state_in_downtime',
            'ack'               => 'service_host_state_is_acknowledged',
            'state'             => $serviceStateCol,
            'display_name'      => 'service_display_name',
            'host_display_name' => 'service_host_display_name'
        );
        $serviceStatus = $this->selectArrayCols($serviceList,$serviceStatusCols);

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

        // TODO: Union, single query?
        Benchmark::measure('Got states for business process ' . $config->getName());

        return $this;
    }

    protected function selectArrayCols ($array, $cols)
    {
        $selectArrayCols = [];
        foreach ($array as $idx => $subArray)
        {
            $tmpArray = [];
            foreach ($cols as $colKey => $colVal)
            {
                $tmpArray[$colKey] = $subArray[$colVal];
            }
            $selectArrayCols[$idx] = (object) $tmpArray;
        }

        return $selectArrayCols;
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

        $node->setAlias($row->display_name);

        if ($node instanceof ServiceNode) {
            $node->setHostAlias($row->host_display_name);
        }
    }
}
