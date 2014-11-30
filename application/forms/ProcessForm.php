<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Web\Form;
use Icinga\Web\Request;
use Icinga\Module\Businessprocess\BpNode;

class ProcessForm extends Form
{
    protected $backend;

    protected $process;

    protected $node;

    protected $objectList = array();

    protected $processList = array();

    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->setup();
    }

    protected function translate($string)
    {
        return mt('businessprocess', $string);
    }

    public function setup()
    {
        $this->addElement('text', 'name', array(
            'label'        => $this->translate('Process name'),
            'required'     => true,
        ));

        $this->addElement('select', 'operator', array(
            'label'        => $this->translate('Operator'),
            'required'     => true,
            'multiOptions' => array(
                'and' => $this->translate('AND'),
                'or ' => $this->translate('OR'),
                'min' => $this->translate('min')
            )
        ));

        $this->addElement('multiselect', 'children', array(
            'label'       => $this->translate('Process components'),
            'required'    => true,
            'size'        => 14,
            'style'       => 'width: 25em;',
            'description' => $this->translate('Hosts, services or other processes that should be part of this business process')
        ));
        $this->addElement('submit', $this->translate('Store'));
    }

    public function setBackend($backend)
    {
        $this->backend = $backend;
        $this->fetchObjectList();
        $this->fillAvailableChildren();
        return $this;
    }

    protected function fillAvailableChildren()
    {
        $this->getElement('children')->setMultiOptions(
            array(
                $this->translate('Other Business Processes') => $this->processList
            ) + $this->objectList
        );

    }

    public function setProcess($process)
    {
        $this->process = $process;
        $this->processList = array();
        foreach ($process->getNodes() as $node) {
            if ($node instanceof BpNode) {
                // TODO: Blacklist parents
                $this->processList[(string) $node] = (string) $node; // display name?
            }
        }
        natsort($this->processList);
        $this->fillAvailableChildren();
        return $this;
    }

    public function setNode($node)
    {
        $this->node = $node;

        $this->setDefaults(array(
            'name'     => (string) $node,
            'children' => array_keys($node->getChildren())
        ));
        return $this;
    }

    protected function fetchObjectList()
    {
        $this->objectList = array();
        $hosts = $this->backend->select()->from('hostStatus', array(
            'hostname'    => 'host_name',
            'in_downtime' => 'host_in_downtime',
            'ack'         => 'host_acknowledged',
            'state'       => 'host_state'
        ))->order('host_name')->getQuery()->fetchAll();

        $services = $this->backend->select()->from('serviceStatus', array(
            'hostname'    => 'host_name',
            'service'     => 'service_description',
            'in_downtime' => 'service_in_downtime',
            'ack'         => 'service_acknowledged',
            'state'       => 'service_state'
        ))->order('host_name')->order('service_description')->getQuery()->fetchAll();

        foreach ($hosts as $host) {
            $this->objectList[$host->hostname] = array(
                $host->hostname . ';Hoststatus' => 'Host Status'
            );
        }

        foreach ($services as $service) {
            $this->objectList[$service->hostname][
                $service->hostname . ';' . $service->service
            ] = $service->service;
        }

        return $this;
    }

    public function onSuccess()
    {
        Notification::success(sprintf($message, $this->getElement('name')->getValue()));
    }
}
