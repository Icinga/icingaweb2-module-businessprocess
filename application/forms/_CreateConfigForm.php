<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Web\Form;
use Icinga\Web\Notification;
use Icinga\Web\Request;

class CreateConfigForm extends Form
{
    public function setup()
    {
        $this->addElement('text', 'name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate('This is the unique identifier of this process'),
        ));

        $this->addElement('text', 'title', array(
            'label'       => $this->translate('Title'),
            'description' => $this->translate('Usually this title will be shown for this process. Equals name if not given'),
        ));

        $this->addElement('select', 'backend_name', array(
            'label'       => $this->translate('Backend'),
            'required'    => true,
            'description' => $this->translate('Icinga Web Monitoring Backend where current object states for this process should be retrieved from'),
            'multiOptions' => array(
                null => $this->translate('Use current default backend'),
            ) + $this->listAvailableBackends()
        ));

        $this->addElement('select', 'state_type', array(
            'label'       => $this->translate('State Type'),
            'required'    => true,
            'description' => $this->translate('Whether this process should be based on Icinga hard or soft states'),
            'multiOptions' => array(
                'hard' => $this->translate('Use HARD states'),
                'soft' => $this->translate('Use SOFT states'),
            )
        ));
        $this->addElement('submit', $this->translate('Store'));
    }

    protected function listAvailableBackends()
    {
        $keys = array_keys(Config::module('monitoring', 'backends')->toArray());
        return array_combine($keys, $keys);
    }

    public function setBackend($backend)
    {
        $this->backend = $backend;
        return $this;
    }

    public function setProcessConfig($config)
    {
        $this->process = $config;
        $this->getElement('name')->setValue($config->getName());

        if ($config->hasTitle()) {
            $this->getElement('title')->setValue($config->getTitle());
        }

        if ($config->hasBackend()) {
            $this->getElement('backend_name')->setValue($config->getBackend()->getName());
        }

        if ($config->usesSoftStates()) {
            $this->getElement('state_type')->setValue('soft');
        } else {
            $this->getElement('state_type')->setValue('hard');
        }

        return $this;
    }

    public function setSession($session)
    {
        $this->session = $session;
        return $this;
    }

    public function onSuccess()
    {
/*
        $storage->storeProcess($bp);
        $modifications = $this->session->get('modifications', array());
        $node = $this->process->getNode($this->getValue('name'));
        $node->setChildNames($this->getValue('children'));
        $node->setOperator($this->getValue('operator'));
        $modifications[$this->process->getName()] = $this->process->toLegacyConfigString();
        $this->session->set('modifications', $modifications);
        $message = 'Process %s has been modified';
        Notification::success(sprintf($message, $this->process->getName()));
*/
    }
}
