<?php

namespace Icinga\Module\Bpapp;

use Exception;

class BusinessProcess
{
    const SOFT_STATE = 0;
    const HARD_STATE = 1;
    protected $ido;
    protected $filename;
    protected $bps;
    protected $state_type = self::HARD_STATE;
    protected $warnings = array();
    protected $nodes = array();
//    protected $object_ids = array();
    protected $root_nodes = array();
    protected $all_checks = array();
    protected $hosts = array();

    public function __construct()
    {
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

    public function retrieveStatesFromBackend($backend)
    {
        $this->backend = $backend;
        // TODO: Split apart, create a dedicated function.
        //       Separate "parse-logic" from "retrieve-state-logic"
        //       Allow DB-based backend
        //       Use IcingaWeb2 Multi-Backend-Support
        $check_results = array();
        $hostFilter = array_keys($this->hosts);
        if ($this->state_type === self::HARD_STATE) {
            $hostStateColumn    = 'host_hard_state';
            $serviceStateColumn = 'service_hard_state';
        } else {
            $hostStateColumn    = 'host_state';
            $serviceStateColumn = 'service_state';
        }
        $hostStatus = $backend->select()->from('hostStatus', array(
            'hostname'    => 'host_name',
            'in_downtime' => 'host_in_downtime',
            'ack'         => 'host_acknowledged',
            'state'       => $hostStateColumn
        ))->where('host_name', $hostFilter)->getQuery()->fetchAll();

        $serviceStatus = $backend->select()->from('serviceStatus', array(
            'hostname'    => 'host_name',
            'service'     => 'service_description',
            'in_downtime' => 'service_in_downtime',
            'ack'         => 'service_acknowledged',
            'state'       => $serviceStateColumn
        ))->where('host_name', $hostFilter)->getQuery()->fetchAll();

        foreach ($serviceStatus + $hostStatus as $row) {
            $key = $row->hostname;
            if ($row->service) {
                $key .= ';' . $row->service;
                // Ignore unused services, we are fetching more than we need
                if (! array_key_exists($key, $this->all_checks)) {
                    continue;
                }
                $node = new ServiceNode($this, $row);
                // $this->object_ids[$row->object_id] = 1;
            } else {
                $key .= ';Hoststatus';
                if (! array_key_exists($key, $this->all_checks)) {
                    continue;
                }
                $node = new HostNode($this, $row);
                // $this->object_ids[$row->object_id] = 1;
            }
            if ($row->state === null) {
                $node = new ServiceNode(
                    $this,
                    (object) array(
                        'hostname' => $row->hostname,
                        'service'  => $row->service,
                        'state'    => 0
                    )
                );
                $node->setMissing();
            }
            if ((int) $row->in_downtime === 1) {
                $node->setDowntime(true);
            }
            if ((int) $row->ack === 1) {
                $node->setAck(true);
            }
            $this->addNode($key, $node);
        }
        ksort($this->root_nodes);
        return $this;
    }

    public function getRootNodes()
    {
        return $this->root_nodes;
    }

    public function hasNode($name)
    {
        return array_key_exists($name, $this->nodes);
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
            $node = new ServiceNode(
                $this,
                (object) array(
                    'hostname' => $host,
                    'service'  => $service,
                    'state'    => 0
                )
            );
            $node->setMissing();
            return $node;
        }

        throw new Exception(
            sprintf('The node "%s" doesn\'t exist', $name)
        );
    }

    protected function addNode($name, Node $node)
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
}
