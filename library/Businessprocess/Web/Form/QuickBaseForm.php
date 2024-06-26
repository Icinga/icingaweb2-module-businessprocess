<?php

namespace Icinga\Module\Businessprocess\Web\Form;

use Icinga\Application\Icinga;
use Icinga\Application\Modules\Module;
use ipl\Html\ValidHtml;
use ipl\I18n\Translation;
use Zend_Form;

abstract class QuickBaseForm extends Zend_Form implements ValidHtml
{
    use Translation;

    /**
     * The Icinga module this form belongs to. Usually only set if the
     * form is initialized through the FormLoader
     *
     * @var ?Module
     */
    protected $icingaModule;

    protected $icingaModuleName;

    private $hintCount = 0;

    public function __construct($options = null)
    {
        $this->callZfConstructor($this->handleOptions($options))
            ->initializePrefixPaths();
    }

    protected function callZfConstructor($options = null)
    {
        parent::__construct($options);
        return $this;
    }

    protected function initializePrefixPaths()
    {
        $this->addPrefixPathsForBusinessprocess();
        if ($this->icingaModule && $this->icingaModuleName !== 'businessprocess') {
            $this->addPrefixPathsForModule($this->icingaModule);
        }
    }

    protected function addPrefixPathsForBusinessprocess()
    {
        $module = Icinga::app()
            ->getModuleManager()
            ->loadModule('businessprocess')
            ->getModule('businessprocess');

        $this->addPrefixPathsForModule($module);
    }

    public function addPrefixPathsForModule(Module $module)
    {
        $basedir = sprintf(
            '%s/%s/Web/Form',
            $module->getLibDir(),
            ucfirst($module->getName())
        );

        $this->addPrefixPaths(array(
            array(
                'prefix'    => __NAMESPACE__ . '\\Element\\',
                'path'      => $basedir . '/Element',
                'type'      => static::ELEMENT
            )
        ));

        return $this;
    }

    public function addHidden($name, $value = null)
    {
        $this->addElement('hidden', $name);
        $el = $this->getElement($name);
        $el->setDecorators(array('ViewHelper'));
        if ($value !== null) {
            $this->setDefault($name, $value);
            $el->setValue($value);
        }
    
        return $this;
    }

    // TODO: Should be an element
    public function addHtmlHint($html, $options = array())
    {
        return $this->addHtml('<div class="hint">' . $html . '</div>', $options);
    }

    public function addHtml($html, $options = array())
    {
        if (array_key_exists('name', $options)) {
            $name = $options['name'];
            unset($options['name']);
        } else {
            $name = '_HINT' . ++$this->hintCount;
        }

        $this->addElement('simpleNote', $name, $options);
        $this->getElement($name)
            ->setValue($html)
            ->setIgnore(true)
            ->setDecorators(array('ViewHelper'));

        return $this;
    }

    public function optionalEnum($enum, $nullLabel = null)
    {
        if ($nullLabel === null) {
            $nullLabel = $this->translate('- please choose -');
        }

        return array(null => $nullLabel) + $enum;
    }

    protected function handleOptions($options = null)
    {
        if ($options === null) {
            return $options;
        }

        if (array_key_exists('icingaModule', $options)) {
            $this->icingaModule = $options['icingaModule'];
            $this->icingaModuleName = $this->icingaModule->getName();
            unset($options['icingaModule']);
        }

        return $options;
    }

    public function setIcingaModule(Module $module)
    {
        $this->icingaModule = $module;
        return $this;
    }

    protected function loadForm($name, Module $module = null)
    {
        if ($module === null) {
            $module = $this->icingaModule;
        }

        return FormLoader::load($name, $module);
    }

    protected function valueIsEmpty($value)
    {
        if (is_array($value)) {
            return empty($value);
        }

        return strlen($value) === 0;
    }
}
