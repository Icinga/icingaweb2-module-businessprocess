<?php

namespace Icinga\Module\Bpapp\Forms;

use Icinga\Web\Form;
use Icinga\Web\Request;
use Icinga\Web\Notification;
use Icinga\Module\Bpapp\BpNode;

class SimulationForm extends Form
{
    protected $backend;

    protected $process;

    protected $node;

    protected $session;

    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->setup();
    }

    protected function translate($string)
    {
        return mt('bpapp', $string);
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

    public function setBackend($backend)
    {
        $this->backend = $backend;
        return $this;
    }

    public function setProcess($process)
    {
        $this->process = $process;
        return $this;
    }

    public function setNode($node)
    {
        $this->node = $node;
        $this->setDefaults(array(
            // TODO: extend descr 'state' => (string) $node->getState(),
            'acknowledged' => $node->isAcknowledged(),
            'in_downtime'  => $node->isInDowntime(),
        ));
        return $this->checkNodeSession();
    }

    public function setSession($session)
    {
        $this->session = $session;
        return $this->checkNodeSession();
    }

    protected function checkNodeSession()
    {
        if ($this->node === null || $this->session === null) {
            return $this;
        }

        $simulations = $this->session->get('simulations', array());
        $node = (string) $this->node;
        if (array_key_exists($node, $simulations)) {
            $this->setDefaults(array(
                'simulate'     => true,
                'state'        => $simulations[$node]->state,
                'in_downtime'  => $simulations[$node]->in_downtime,
                'acknowledged' => $simulations[$node]->acknowledged,
            ));
        }

        return $this;
    }

    public function onSuccess()
    {
        $node = (string) $this->node;
        $simulations = $this->session->get('simulations', array());

        if ($this->getValue('state') === '') {
            if (array_key_exists($node, $simulations)) {
                Notification::success($this->translate('Simulation has been removed'));
                unset($simulations[$node]);
                $this->session->set('simulations', $simulations);
//                $this->session->write();
            }
        } else {
            // Notification::success($this->translate('Simulation has been updated'));
            $simulations[$node] = (object) array(
                'state'        => $this->getValue('state'),
                'acknowledged' => $this->getValue('acknowledged'),
                'in_downtime' => $this->getValue('in_downtime'),
            );
            $this->session->set('simulations', $simulations);
//            $this->session->write();
        }
    }
}
