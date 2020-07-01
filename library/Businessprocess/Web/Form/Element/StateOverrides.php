<?php

namespace Icinga\Module\Businessprocess\Web\Form\Element;

class StateOverrides extends FormElement
{
    public $helper = 'formStateOverrides';

    /** @var array The overridable states */
    protected $states;

    /**
     * Set the overridable states
     *
     * @param array $states
     *
     * @return $this
     */
    public function setStates(array $states)
    {
        $this->states = $states;

        return $this;
    }

    /**
     * Get the overridable states
     *
     * @return array
     */
    public function getStates()
    {
        return $this->states;
    }

    public function init()
    {
        $this->setIsArray(true);
    }

    public function setValue($value)
    {
        $cleanedValue = [];

        if (! empty($value)) {
            foreach ($value as $from => $to) {
                if ((int) $from !== (int) $to) {
                    $cleanedValue[$from] = $to;
                }
            }
        }

        return parent::setValue($cleanedValue);
    }
}
