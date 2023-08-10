<?php

namespace Icinga\Module\Businessprocess\Web\Form\Element;

use ipl\Html\Attributes;
use ipl\Html\FormElement\FieldsetElement;

class IplStateOverrides extends FieldsetElement
{
    /** @var array */
    protected $options = [];

    /**
     * Set the options show
     *
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get the options to show
     *
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    public function getValues()
    {
        $cleanedValue = parent::getValues();

        if (! empty($cleanedValue)) {
            foreach ($cleanedValue as $from => $to) {
                if ((int) $from === (int) $to) {
                    unset($cleanedValue[$from]);
                }
            }
        }

        return $cleanedValue;
    }

    protected function assemble()
    {
        $states = $this->getOptions();
        foreach ($states as $state => $label) {
            if ($state === 0) {
                continue;
            }

            $this->addElement('select', $state, [
                'label' => $label,
                'value' => $state,
                'options' => [$state => $this->translate('Keep actual state')] + $states
            ]);
        }
    }

    protected function registerAttributeCallbacks(Attributes $attributes)
    {
        parent::registerAttributeCallbacks($attributes);

        $this->getAttributes()
            ->registerAttributeCallback('options', null, [$this, 'setOptions']);
    }
}
