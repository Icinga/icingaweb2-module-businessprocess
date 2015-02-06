<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Data\Filter\Filter;
use Exception;

class BusinessProcess
{
    const SOFT_STATE = 0;
    const HARD_STATE = 1;

    /**
     * Monitoring backend to retrieve states from
     *
     * @var MonitoringBackend
     */
    protected $backend;

    protected $name;
    protected $state_type = self::HARD_STATE;
    protected $warnings = array();
    protected $nodes = array();
    protected $root_nodes = array();
    protected $all_checks = array();
    protected $hosts = array();
    protected $simulationMode = false;
    protected $editMode = false;

    public function __construct()
    {
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function hasBeenChanged()
    {
        return false;
    }

    public function setSimulationMode($mode = true)
    {
        $this->simulationMode = (bool) $mode;
        return $this;
    }

    public function isSimulationMode()
    {
        return $this->simulationMode;
    }

    public function setEditMode($mode = true)
    {
        $this->editMode = (bool) $mode;
        return $this;
    }

    public function isEditMode()
    {
        return $this->editMode;
    }
/*
    public function getObjectIds()
    {
        return array_keys($this->object_ids);
    }
*/

    public function useSoftStates()
    {
        $this->state_type = self::SOFT_STATE;
        return $this;
    }

    public function useHardStates()
    {
        $this->state_type = self::HARD_STATE;
        return $this;
    }

    public function addRootNode($name)
    {
        $this->root_nodes[$name] = $this->getNode($name);
        return $this;
    }

    public function retrieveStatesFromBackend(MonitoringBackend $backend)
    {
        $this->backend = $backend;
        // TODO: Split apart, create a dedicated function.
        //       Separate "parse-logic" from "retrieve-state-logic"
        //       Allow DB-based backend
        //       Use IcingaWeb2 Multi-Backend-Support
        $check_results = array();
        $hostFilter = array_keys($this->hosts);
        if ($this->state_type === self::HARD_STATE) {
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
        $filter = Filter::matchAny();
        foreach ($hostFilter as $host) {
            $filter->addFilter(Filter::where('host_name', $host));
        }

        $hostStatus = $backend->select()->from('hostStatus', array(
            'hostname'          => 'host_name',
            'last_state_change' => $hostStateChangeColumn, 
            'in_downtime'       => 'host_in_downtime',
            'ack'               => 'host_acknowledged',
            'state'             => $hostStateColumn
        ))->applyFilter($filter)->getQuery()->fetchAll();

        $serviceStatus = $backend->select()->from('serviceStatus', array(
            'hostname'          => 'host_name',
            'service'           => 'service_description',
            'last_state_change' => $serviceStateChangeColumn, 
            'in_downtime'       => 'service_in_downtime',
            'ack'               => 'service_acknowledged',
            'state'             => $serviceStateColumn
        ))->applyFilter($filter)->getQuery()->fetchAll();

        foreach ($serviceStatus + $hostStatus as $row) {
            $key = $row->hostname;
            if ($row->service) {
                $key .= ';' . $row->service;
            } else {
                $key .= ';Hoststatus';
            }
            // We fetch more states than we need, so skip unknown ones
            if (! $this->hasNode($key)) continue;
            $node = $this->getNode($key);

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
        ksort($this->root_nodes);
        return $this;
    }

    public function getChildren()
    {
        return $this->getRootNodes();
    }

    public function countChildren()
    {
        return count($this->root_nodes);
    }

    public function getRootNodes()
    {
        ksort($this->root_nodes);
        return $this->root_nodes;
    }

    public function getNodes()
    {
        return $this->nodes;
    }

    public function hasNode($name)
    {
        return array_key_exists($name, $this->nodes);
    }

    public function createService($host, $service)
    {
        $node = new ServiceNode(
            $this,
            (object) array(
                'hostname' => $host,
                'service'  => $service
            )
        );
        $this->nodes[$host . ';' . $service] = $node;
        $this->hosts[$host] = true;
        return $node;
    }

    public function createHost($host)
    {
        $node = new HostNode($this, (object) array('hostname' => $host));
        $this->nodes[$host . ';Hoststatus'] = $node;
        $this->hosts[$host] = true;
        return $node;
    }

    public function getNode($name)
    {
        if (array_key_exists($name, $this->nodes)) {
            return $this->nodes[$name];
        }

        // Fallback: if it is a service, create an empty one:
        $this->warn(sprintf('The node "%s" doesn\'t exist', $name));
        $pos = strpos($name, ';');
        if ($pos !== false) {
            $host = substr($name, 0, $pos);
            $service = substr($name, $pos + 1);
            return $this->createService($host, $service);
        }

        throw new Exception(
            sprintf('The node "%s" doesn\'t exist', $name)
        );
    }

    public function addObjectName($name)
    {
        $this->all_checks[$name] = 1;
        return $this;
    }

    public function addNode($name, Node $node)
    {
        if (array_key_exists($name, $this->nodes)) {
            $this->warn(
                sprintf(
                    'Node "%s" has been defined twice',
                    $name
                )
            );
        }

        $this->nodes[$name] = $node;
        return $this;
    }

    public function hasWarnings()
    {
        return ! empty($this->warnings);
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    protected function warn($msg)
    {
        if (isset($this->parsing_line_number)) {
            $this->warnings[] = sprintf(
                'Parser waring on %s:%s: %s',
                $this->filename,
                $this->parsing_line_number,
                $msg
            );
        } else {
            $this->warnings[] = $msg;
        }
    }

    public function toLegacyConfigString()
    {
        $conf = '';
        foreach ($this->getChildren() as $child) {
            $conf .= $child->toLegacyConfigString();
        }
        return $conf;
    }

    public function renderHtml($view)
    {
        $html = '<div class="bp">';
        foreach ($this->getRootNodes() as $name => $node) {
            // showNode($this, $node, $this->slas, $this->opened, 'bp_')
            $html .= $node->renderHtml($view);
        }
        $html .= '</div>';
        return $html;
    }
}
