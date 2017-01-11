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
        $states = $this->enumStateNames();

        // TODO: Fetch state from object
        if ($this->simulatedNode) {
            $simulatedState = $this->simulatedNode->getState();
            $states[$simulatedState] = sprintf(
                '%s (%s)',
                $this->node->getStateName($simulatedState),
                $this->translate('Current simulation')
            );
            $node = $this->simulatedNode;
            $hasSimulation = true;
        } else {
            $hasSimulation = false;
            $node = $this->node;
        }

        $view = $this->getView();
        if ($hasSimulation) {
            $title = $this->translate('Modify simulation for %s');
        } else {
            $title = $this->translate('Add simulation for %s');
        }
        $this->addHtml(
            '<h2>'
            . $view->escape(sprintf($title, $node->getAlias()))
            . '</h2>'
        );

        $this->addElement('select', 'state', array(
            'label'        => $this->translate('State'),
            'multiOptions' => $states,
            'class'        => 'autosubmit',
            'value'        => $this->simulatedNode ? $node->getState() : null,
        ));
        if (in_array($this->getSentValue('state'), array('0', '99'))) {
            return;
        }
        if ($hasSimulation || ctype_digit($this->getSentValue('state'))) {
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

        $name = $this->node->getName();
        if ($simulation->hasNode($name)) {
            $this->simulatedNode = clone($this->node);
            $s = $simulation->getNode($name);
            $this->simulatedNode->setState($s->state)
                ->setAck($s->acknowledged)
                ->setDowntime($s->in_downtime)
                ->setMissing(false);
        }

        return $this;
    }

    public function onSuccess()
    {
        $nodeName = $this->node->getName();

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

    /**
     * @return array
     */
    protected function enumStateNames()
    {
        $states = array(
            null => sprintf(
                $this->translate('Use current state (%s)'),
                $this->translate($this->node->getStateName())
            )
        ) + $this->node->enumStateNames();

        return $states;
    }
}
