<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Form;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Web\Notification;
use Icinga\Web\Request;

class SimulationForm extends Form
{
    protected $node;

    protected $simulation;

    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->setup();
    }

    public function setup()
    {
        $this->addElement('select', 'state', array(
            'label'        => $this->translate('State'),
            'multiOptions' => array(
                ''  => $this->translate('Use current state'),
                '0' => $this->translate('OK'),
                '1' => $this->translate('WARNING'),
                '2' => $this->translate('CRITICAL'),
                '3' => $this->translate('UNKNOWN'),
                '99' => $this->translate('PENDING'),
            )
        ));

        $this->addElement('text', 'output', array(
            'label'        => $this->translate('Plugin output'),
        ));

        $this->addElement('checkbox', 'acknowledged', array(
            'label' => $this->translate('Acknowledged'),
        ));

        $this->addElement('checkbox', 'in_downtime', array(
            'label' => $this->translate('In downtime'),
        ));

        $this->addElement('submit', $this->translate('Apply'));
    }

    public function setNode($node)
    {
        $this->node = $node;
        $this->setDefaults(array(
            // TODO: extend descr 'state' => (string) $node->getState(),
            'acknowledged' => $node->isAcknowledged(),
            'in_downtime'  => $node->isInDowntime(),
        ));
        return $this->checkDefaults();
    }

    public function setSimulation($simulation)
    {
        $this->simulation = $simulation;
        return $this->checkDefaults();
    }

    protected function checkDefaults()
    {
        if ($this->node !== null
            && $this->simulation !== null
            && $this->simulation->hasNode((string) $this->node)
        ) {
            $this->setDefaults((array) $this->simulation->getNode((string) $this->node));
        }
        return $this;
    }

    public function onSuccess()
    {
        $node = (string) $this->node;

        if ($this->getValue('state') === '') {
            if ($this->simulation->remove($node)) {
                Notification::success($this->translate('Simulation has been removed'));
            }
        } else {
                Notification::success($this->translate('Simulation has been set'));
            $this->simulation->set($node, (object) array(
                'state'        => $this->getValue('state'),
                'acknowledged' => $this->getValue('acknowledged'),
                'in_downtime'  => $this->getValue('in_downtime'),
            ));
        }
    }
}
