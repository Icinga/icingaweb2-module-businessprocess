<?php

namespace Icinga\Module\Businessprocess\Forms;

use Exception;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Storage\LegacyConfigParser;
use Icinga\Module\Businessprocess\Web\Form\BpConfigBaseForm;
use Icinga\Web\Notification;

class BpUploadForm extends BpConfigBaseForm
{
    protected $backend;

    protected $node;

    protected $objectList = array();

    protected $processList = array();

    protected $deleteButtonName;

    private $sourceCode;

    /** @var BpConfig */
    private $uploadedConfig;

    public function setup()
    {
        $this->showUpload();
        if ($this->hasSource()) {
            $this->showDetails();
        }
    }

    protected function showDetails()
    {
        $this->addElement('text', 'name', array(
            'label'       => $this->translate('Name'),
            'required'    => true,
            'description' => $this->translate(
                'This is the unique identifier of this process'
            ),
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
        ));

        $this->addElement('textarea', 'source', array(
            'label'       => $this->translate('Source'),
            'description' => $this->translate(
                'Business process source code'
            ),
            'value' => $this->sourceCode,
            'class' => 'preformatted smaller',
            'rows'  => 7,
        ));

        $this->getUploadedConfig();

        $this->setSubmitLabel(
            $this->translate('Store')
        );
    }

    public function getUploadedConfig()
    {
        if ($this->uploadedConfig === null) {
            $this->uploadedConfig = $this->parseSubmittedSourceCode();
        }

        return $this->uploadedConfig;
    }

    protected function parseSubmittedSourceCode()
    {
        $code = $this->getSentValue('source');
        $name = $this->getSentValue('name', '<new config>');
        if (empty($code)) {
            $code = $this->sourceCode;
        }

        try {
            $config = LegacyConfigParser::parseString($name, $code);

            if ($config->hasErrors()) {
                foreach ($config->getErrors() as $error) {
                    $this->addError($error);
                }
            }
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return null;
        }

        return $config;
    }

    protected function hasSource()
    {
        if ($this->hasBeenSent() && $source = $this->getSentValue('source')) {
            $this->sourceCode = $source;
        } else {
            $this->processUploadedSource();
        }

        if (empty($this->sourceCode)) {
            return false;
        } else {
            $this->removeElement('uploaded_file');
            return true;
        }
    }

    protected function showUpload()
    {
        $this->setAttrib('enctype', 'multipart/form-data');

        $this->addElement('file', 'uploaded_file', array(
            'label'       => $this->translate('File'),
            'destination' => $this->getTempDir(),
            'required'    => true,
        ));

        /** @var \Zend_Form_Element_File $el */
        $el = $this->getElement('uploaded_file');
        $el->setValueDisabled(true);

        $this->setSubmitLabel(
            $this->translate('Next')
        );
    }

    protected function getTempDir()
    {
        return sys_get_temp_dir();
    }

    protected function processUploadedSource()
    {
        /** @var \Zend_Form_Element_File $el */
        $el = $this->getElement('uploaded_file');

        if ($el && $this->hasBeenSent()) {
            $tmpdir = $this->getTempDir();
            $tmpfile = tempnam($tmpdir, 'bpupload_');

            // TODO: race condition, try to do this without unlinking here
            unlink($tmpfile);

            $el->addFilter('Rename', $tmpfile);
            if ($el->receive()) {
                $this->sourceCode = file_get_contents($tmpfile);
                unlink($tmpfile);
            } else {
                foreach ($el->file->getMessages() as $error) {
                    $this->addError($error);
                }
            }
        }

        return $this;
    }

    public function onSuccess()
    {
        $config = $this->getUploadedConfig();
        $name = $config->getName();

        if ($this->storage->hasProcess($name)) {
            $this->addError(sprintf(
                $this->translate('A process named "%s" already exists'),
                $name
            ));

            return;
        }

        if (! $this->prepareMetadata($config)) {
            return;
        }

        $this->storage->storeProcess($config);
        Notification::success(sprintf('Process %s has been stored', $name));
    }
}
