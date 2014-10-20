<?php

namespace Icinga\Module\Bpapp;

use Exception;

class BusinessProcess
{
    const SOFT_STATE = 0;
    const HARD_STATE = 1;
    protected $ido;
    protected $filename;
    protected $parsing_line_number;
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

    public static function parse($filename)
    {
        $bp = new BusinessProcess();
        $bp->filename = $filename;
        $bp->doParse();
        return $bp;
    }

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

    protected function doParse()
    {
        $fh = @fopen($this->filename, 'r');
        if (! $fh) {
            throw new Exception('Could not open ' . $this->filename);
        }

        $this->parsing_line_number = 0;
        while ($line = fgets($fh)) {
            $line = trim($line);

            $this->parsing_line_number++;

            if (preg_match('~^#~', $line)) {
                continue;
            }

            if (preg_match('~^$~', $line)) {
                continue;
            }

            if (preg_match('~^display~', $line)) {
                list($display, $name, $desc) = preg_split('~\s*;\s*~', substr($line, 8), 3);
                $node = $this->getNode($name)->setAlias($desc)->setDisplay($display);
                if ($display > 0) {
                    $this->root_nodes[$name] = $node;
                }
            }

            if (preg_match('~^external_info~', $line)) {
                list($name, $script) = preg_split('~\s*;\s*~', substr($line, 14), 2);
                $node = $this->getNode($name)->setInfoCommand($script);
            }

            if (preg_match('~^info_url~', $line)) {
                list($name, $url) = preg_split('~\s*;\s*~', substr($line, 9), 2);
                $node = $this->getNode($name)->setUrl($url);
            }

            if (strpos($line, '=') === false) {
                continue;
            }
            
            list($name, $value) = preg_split('~\s*=\s*~', $line, 2);

            if (strpos($name, ';') !== false) {
                $this->parseError('No semicolon allowed in varname');
            }

            $op = '&';
            if (preg_match_all('~([\|\+&])~', $value, $m)) {
                $op = implode('', $m[1]);
                for ($i = 1; $i < strlen($op); $i++) {
                    if ($op[$i] !== $op[$i - 1]) {
                        $this->parseError('Mixing operators is not allowed');
                    }
                }
            }
            $op = $op[0];
            $op_name = $op;

            if ($op === '+') {
                if (! preg_match('~^(\d+)\s*of:\s*(.+?)$~', $value, $m)) {
                    $this->parseError('syntax: <var> = <num> of: <var1> + <var2> [+ <varn>]*');
                }
                $op_name = $m[1];
                $value   = $m[2];
            }
            $cmps = preg_split('~\s*\\' . $op . '\s*~', $value);

            foreach ($cmps as & $val) {
                if (strpos($val, ';') !== false) {
                    list($host, $service) = preg_split('~;~', $val, 2);
                    $this->all_checks[$val] = 1;
                    $this->hosts[$host] = 1;
                }
            }
            $node = new BpNode($this, (object) array(
                'name'        => $name,
                'operator'    => $op_name,
                'child_names' => $cmps
            ));
            $this->addNode($name, $node);
        }

        fclose($fh);
        unset($this->parsing_line_number);
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
            $db_states = $this->backend/*->module('Bpapp')*/
                ->fetchHardStatesForBpHosts(array_keys($this->hosts));
        } else {
// TOM 2014
//            $db_states = $this->backend/*->module('Bpapp')*/
//                ->fetchSoftStatesForBpHosts(array_keys($this->hosts));
          $hostStatus = $backend->select()->from(
              'hostStatus',
              array(
                  'hostname'    => 'host_name',
                  'in_downtime' => 'host_in_downtime',
                  'ack'         => 'host_acknowledged',
                  'state'       => 'host_state'
              )
          )->where('host_name', $hostFilter)->getQuery()->fetchAll();
          $serviceStatus = $backend->select()->from(
              'serviceStatus',
              array(
                  'hostname'    => 'host_name',
                  'service'     => 'service_description',
                  'in_downtime' => 'service_in_downtime',
                  'ack'         => 'service_acknowledged',
                  'state'       => 'service_state'
              )
          )->where('host_name', $hostFilter)->getQuery()->fetchAll();

        }

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

    protected function parseError($msg)
    {
        throw new Exception(
            sprintf(
                'Parse error on %s:%s: %s',
                $this->filename,
                $this->parsing_line_number,
                $msg
            )
        );
    }
}
