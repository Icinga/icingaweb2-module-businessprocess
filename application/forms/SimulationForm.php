<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Web\Request;

class SimulationForm extends QuickForm
{
    protected $node;

    protected $simulatedNode;

    protected $simulation;

    public function setup()
    {
        $states = array(
            null  => sprintf(
                $this->translate('Use current state (%s)'),
                $this->translate($this->node->getStateName())
            ),
            '0'  => $this->translate('OK'),
            '1'  => $this->translate('WARNING'),
            '2'  => $this->translate('CRITICAL'),
            '3'  => $this->translate('UNKNOWN'),
            '99' => $this->translate('PENDING'),
        );

        // TODO: Fetch state from object
        if ($this->simulatedNode) {
            $states[$this->simulatedNode->getState()] . sprintf(' (%s)', $this->translate('Current simulation'));
            $node = $this->simulatedNode;
        } else {
            $node = $this->node;
        }

        $this->addElement('select', 'state', array(
            'label'        => $this->translate('State'),
            'multiOptions' => $states,
            'value'        => $this->simulatedNode ? $node->getState() : null,
        ));

        $this->addElement('checkbox', 'acknowledged', array(
            'label' => $this->translate('Acknowledged'),
            'value' => $node->isAcknowledged(),
        ));

        $this->addElement('checkbox', 'in_downtime', array(
            'label' => $this->translate('In downtime'),
            'value' => $node->isInDowntime(),
        ));

        $this->setSubmitLabel($this->translate('Apply'));
    }

    public function setNode($node)
    {
        $this->node = $node;
        return $this;

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

        $nodeName = (string) $this->node;
        if ($simulation->hasNode($nodeName)) {
            $this->simulatedNode = clone($this->node);
            $s = $simulation->getNode($nodeName);
            $this->simulatedNode->setState($s->state)
                ->setAck($s->acknowledged)
                ->setDowntime($s->in_downtime)
                ->setMissing(false);
        }

        return $this;
    }

    public function onSuccess()
    {
        $nodeName = (string) $this->node;

        if (ctype_digit($this->getValue('state'))) {
            $this->notifySuccess($this->translate('Simulation has been set'));
            $this->simulation->set($nodeName, (object) array(
                'state'        => $this->getValue('state'),
                'acknowledged' => $this->getValue('acknowledged'),
                'in_downtime'  => $this->getValue('in_downtime'),
            ));
        } else {
            if ($this->simulation->remove($nodeName)) {
                $this->notifySuccess($this->translate('Simulation has been removed'));
            }
        }
    }
}
