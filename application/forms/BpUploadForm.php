<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Web\Notification;

class BpUploadForm extends QuickForm
{
    /** @var LegacyStorage */
    protected $storage;

    protected $backend;

    /** @var BpConfig */
    protected $config;

    protected $node;

    protected $objectList = array();

    protected $processList = array();

    protected $deleteButtonName;

    public function setup()
    {
        $this->addElement('text', 'name', array(
            'label'       => $this->translate('Name'),
            // 'required'    => true,
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

        $this->setAttrib('enctype', 'multipart/form-data');

        $tmpdir = sys_get_temp_dir();

        $this->addElement('file', 'uploaded_file', array(
            'label'       => $this->translate('File'),
            'destination' => $tmpdir,
            'required'    => true,
        ));

        /** @var \Zend_Form_Element_File $el */
        $el = $this->getElement('uploaded_file');
        $el->setValueDisabled(true);

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
                $this->translate('Upload')
            );
/*
            $label = $this->translate('Delete');
            $el = $this->createElement('submit', $label)
                ->setLabel($label)
                ->setDecorators(array('ViewHelper'));
            $this->deleteButtonName = $el->getName();
            $this->addElement($el);
*/
        }
    }

    protected function listAvailableBackends()
    {
        $keys = array_keys(Config::module('monitoring', 'backends')->toArray());
        return array_combine($keys, $keys);
    }

    public function setStorage(LegacyStorage $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    public function setProcessConfig(BpConfig $config)
    {
        $this->config = $config;
        return $this;
    }

    public function onSuccess()
    {

        $tmpdir = sys_get_temp_dir();
        $tmpfile = tempnam($tmpdir, 'bpupload_');
        unlink($tmpfile);
        $values = $this->getValues();
        /** @var \Zend_Form_Element_File $el */
        $el = $this->getElement('uploaded_file');
        var_dump($el->getFileName());
        var_dump($tmpfile);
        $el->addFilter('Rename', $tmpfile);
        if (!$el->receive()) {
            print_r($el->file->getMessages());
        }
        echo file_get_contents($tmpfile);
        unlink($tmpfile);
        echo "DONE\n";
        exit;
        $name    = $this->getValue('name');
        $title   = $this->getValue('title');
        $backend = $this->getValue('backend');
        /*
        onSuccess:
        $uploadedData = $form->getValues();
        $fullFilePath = $form->file->getFileName();
         */
        var_dump($this->getValues());

        exit;

        if ($this->config === null) {
            // New config
            $config = new BpConfig();
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
