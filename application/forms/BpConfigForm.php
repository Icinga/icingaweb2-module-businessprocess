<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Web\Form\BpConfigBaseForm;

class BpConfigForm extends BpConfigBaseForm
{
    protected $deleteButtonName;

    public function setup()
    {
        $this->addElement('text', 'name', array(
            'label' => $this->translate('ID'),
            'required'    => true,
            'validators' => array(
                array(
                    'validator' => 'StringLength',
                    'options' => array(
                        'min' => 2,
                        'max' => 40
                    )
                ),
                array(
                    'validator' => 'Regex',
                    'options' => array(
                        'pattern' => '/^[a-zA-Z0-9](?:[a-zA-Z0-9 ._-]*)?[a-zA-Z0-9_]$/'
                    )
                )
            ),
            'description' => $this->translate(
                'This is the unique identifier of this process'
            ),
        ));

        $this->addElement('text', 'Title', array(
            'label'       => $this->translate('Display Name'),
            'description' => $this->translate(
                'Usually this name will be shown for this process. Equals ID'
                . ' if not given'
            ),
        ));

        $this->addElement('textarea', 'Description', array(
            'label'       => $this->translate('Description'),
            'description' => $this->translate(
                'A slightly more detailed description for this process, about 100-150 characters long'
            ),
            'rows' => 4,
        ));

        $this->addElement('select', 'Backend', array(
            'label'       => $this->translate('Backend'),
            'description' => $this->translate(
                'Icinga Web Monitoring Backend where current object states for'
                . ' this process should be retrieved from'
            ),
            'multiOptions' => array(
                null => $this->translate('Use the configured default backend'),
            ) + $this->listAvailableBackends()
        ));

        $this->addElement('select', 'Statetype', array(
            'label'       => $this->translate('State Type'),
            'required'    => true,
            'description' => $this->translate(
                'Whether this process should be based on Icinga hard or soft states'
            ),
            'multiOptions' => array(
                'soft' => $this->translate('Use SOFT states'),
                'hard' => $this->translate('Use HARD states'),
            )
        ));

        $this->addElement('select', 'AddToMenu', array(
            'label'       => $this->translate('Add to menu'),
            'required'    => true,
            'description' => $this->translate(
                'Whether this process should be linked in the main Icinga Web 2 menu'
            ),
            'multiOptions' => array(
                'yes' => $this->translate('Yes'),
                'no'  => $this->translate('No'),
            )
        ));

        if ($this->config === null) {
            $this->setSubmitLabel(
                $this->translate('Add')
            );
        } else {
            $config = $this->config;

            $meta = $config->getMetadata();
            foreach ($meta->getProperties() as $k => $v) {
                if ($el = $this->getElement($k)) {
                    $el->setValue($v);
                }
            }
            $this->getElement('name')
                 ->setValue($config->getName())
                 ->setAttrib('readonly', true);

            $this->setSubmitLabel(
                $this->translate('Store')
            );

            $label = $this->translate('Delete');
            $el = $this->createElement('submit', $label, array(
                'data-base-target' => '_main'
            ))->setLabel($label)->setDecorators(array('ViewHelper'));
            $this->deleteButtonName = $el->getName();
            $this->addElement($el);
        }
    }

    protected function onSetup()
    {
        $this->getElement($this->getSubmitLabel())->setAttrib('data-base-target', '_main');
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
        $name = $this->getValue('name');

        if ($this->config === null) {
            if ($this->storage->hasProcess($name)) {
                $this->addError(sprintf(
                    $this->translate('A process named "%s" already exists'),
                    $name
                ));

                return;
            }

            // New config
            $config = new BpConfig();
            $config->setName($name);

            if (! $this->prepareMetadata($config)) {
                return;
            }

            $this->setSuccessUrl(
                $this->getSuccessUrl()->setParams(
                    array('config' => $name, 'unlocked' => true)
                )
            );
            $this->setSuccessMessage(sprintf('Process %s has been created', $name));
        } else {
            $config = $this->config;
            $this->setSuccessMessage(sprintf('Process %s has been stored', $name));
        }
        $meta = $config->getMetadata();
        foreach ($this->getValues() as $key => $value) {
            if ($key !== 'Backend' && ($value === null || $value === '')) {
                continue;
            }
            if ($meta->hasKey($key)) {
                $meta->set($key, $value);
            }
        }

        $this->storage->storeProcess($config);
        $config->clearAppliedChanges();
        parent::onSuccess();
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
