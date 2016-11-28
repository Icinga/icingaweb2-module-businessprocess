<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\MonitoredNode;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;

class SimulationForm extends QuickForm
{
    /** @var MonitoredNode */
    protected $node;

    /** @var MonitoredNode */
    protected $simulatedNode;

    /** @var Simulation */
    protected $simulation;

    public function setup()
    {
        $states = array_merge(
            array(
                null => sprintf(
                    $this->translate('Use current state (%s)'),
                    $this->translate($this->node->getStateName())
                )
            ),
            $this->node->enumStateNames()
        );

        // TODO: Fetch state from object
        if ($this->simulatedNode) {
            $simulatedState = $this->simulatedNode->getState();
            $states[$simulatedState] = sprintf(
                '%s (%s)',
                $simulatedState,
                $this->translate('Current simulation')
            );
            $node = $this->simulatedNode;
        } else {
            $node = $this->node;
        }

        $this->addHtml('<h2>Configure this simulation</h2>');

        $this->addElement('select', 'state', array(
            'label'        => $this->translate('State'),
            'multiOptions' => $states,
            'class'        => 'autosubmit',
            'value'        => $this->simulatedNode ? $node->getState() : null,
        ));
        if (ctype_digit($this->getSentValue('state'))) {
            $this->addElement('checkbox', 'acknowledged', array(
                'label' => $this->translate('Acknowledged'),
                'value' => $node->isAcknowledged(),
            ));

            $this->addElement('checkbox', 'in_downtime', array(
                'label' => $this->translate('In downtime'),
                'value' => $node->isInDowntime(),
            ));
        }

        $this->setSubmitLabel($this->translate('Apply'));
    }

    public function setNode($node)
    {
        $this->node = $node;
        return $this;
    }

    public function setSimulation(Simulation $simulation)
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
        $this->redirectOnSuccess();
    }
}
