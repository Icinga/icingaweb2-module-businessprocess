<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Web\Notification;
use Icinga\Web\Request;
use Icinga\Web\Url;

class BpConfigForm extends QuickForm
{
    protected $storage;

    protected $backend;

    protected $config;

    protected $node;

    protected $objectList = array();

    protected $processList = array();

    protected $deleteButtonName;

    public function setup()
    {

        $this->addElement('text', 'name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate(
                'This is the unique identifier of this process'
            ),
        ));

        $this->addElement('text', 'title', array(
            'label'       => $this->translate('Title'),
            'description' => $this->translate(
                'Usually this title will be shown for this process. Equals name'
                . ' if not given'
            ),
        ));

        $this->addElement('select', 'backend_name', array(
            'label'       => $this->translate('Backend'),
            'description' => $this->translate(
                'Icinga Web Monitoring Backend where current object states for'
                . ' this process should be retrieved from'
            ),
            'multiOptions' => array(
                null => $this->translate('Use the configured default backend'),
            ) + $this->listAvailableBackends()
        ));

        $this->addElement('select', 'state_type', array(
            'label'       => $this->translate('State Type'),
            'required'    => true,
            'description' => $this->translate(
                'Whether this process should be based on Icinga hard or soft states'
            ),
            'multiOptions' => array(
                'hard' => $this->translate('Use HARD states'),
                'soft' => $this->translate('Use SOFT states'),
            )
        ));

        if ($this->config === null) {
            $this->setSubmitLabel(
                $this->translate('Add')
            );
        } else {
            $config = $this->config;

            $this->getElement('name')
                 ->setValue($config->getName())
                 ->setAttrib('readonly', true);

            if ($config->hasTitle()) {
                $this->getElement('title')->setValue($config->getTitle());
            }

            if ($config->hasBackend()) {
                $this->getElement('backend_name')->setValue(
                    $config->getBackend()->getName()
                );
            }
            if ($config->usesSoftStates()) {
                $this->getElement('state_type')->setValue('soft');
            } else {
                $this->getElement('state_type')->setValue('hard');
            }

            $this->setSubmitLabel(
                $this->translate('Store')
            );
            $label = $this->translate('Delete');
            $el = $this->createElement('submit', $label)
                ->setLabel($label)
                ->setDecorators(array('ViewHelper'));
            $this->deleteButtonName = $el->getName();
            $this->addElement($el);
        }
    }

    protected function listAvailableBackends()
    {
        $keys = array_keys(Config::module('monitoring', 'backends')->toArray());
        return array_combine($keys, $keys);
    }

    public function setStorage($storage)
    {
        $this->storage = $storage;
        return $this;
    }

    public function setProcessConfig($config)
    {
        $this->config = $config;
        return $this;
        return $this;
    }

    protected function onRequest()
    {
        $name = $this->getValue('name');

        if ($this->shouldBeDeleted()) {
            $this->config->clearAppliedChanges();
            $this->storage->deleteProcess($name);
            $this->setSuccessUrl('businessprocess');
            $this->redirectOnSuccess(sprintf('Process %s has been deleted', $name));
        }
    }

    public function onSuccess()
    {
        $name    = $this->getValue('name');
        $title   = $this->getValue('title');
        $backend = $this->getValue('backend');

        if ($this->config === null) {
            // New config
            $config = new BusinessProcess();
            $config->setName($name);
            if ($title) {
                $config->setTitle($title);
            }
            if ($backend) {
                $config->setBackendName($backend);
            }
            if ($this->getValue('state_type') === 'soft') {
                $config->useSoftStates();
            } else {
                $config->useHardStates();
            }
            $this->storage->storeProcess($config);
            $config->clearAppliedChanges();
            $this->setSuccessUrl(
                $this->getSuccessUrl()->setParams(
                    array('config' => $name, 'unlocked' => true)
                )
            );

            $this->redirectOnSuccess(sprintf('Process %s has been created', $name));
        } else {
            $config = $this->config;
            if ($title) {
                $config->setTitle($title);
            }
            if ($backend) {
                $config->setBackendName($backend);
            }
            if ($this->getValue('state_type') === 'soft') {
                $config->useSoftStates();
            } else {
                $config->useHardStates();
            }

            $this->storage->storeProcess($config);
            $config->clearAppliedChanges();
            $this->getSuccessUrl()->setParam('config', $name);
            Notification::success(sprintf('Process %s has been stored', $name));
        }
    }

    public function hasDeleteButton()
    {
        return $this->deleteButtonName !== null;
    }

    public function shouldBeDeleted()
    {
        if (! $this->hasDeleteButton()) {
            return false;
        }

        $name = $this->deleteButtonName;
        return $this->getSentValue($name) === $this->getElement($name)->getLabel();
    }
}
