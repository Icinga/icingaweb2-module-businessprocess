<?php

namespace Icinga\Module\Businessprocess\Web\Component;

class Attribute extends Component
{
    /** @var string */
    protected $name;

    /** @var string|array */
    protected $value;

    /**
     * Attribute constructor.
     *
     * @param $name
     * @param $value
     */
    public function __construct($name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    /**
     * @param $name
     * @param $value
     * @return static
     */
    public static function create($name, $value)
    {
        return new static($name, $value);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function render()
    {
        return sprintf(
            '%s="%s"',
            $this->renderName(),
            $this->renderValue()
        );
    }

    /**
     * @return string
     */
    public function renderName()
    {
        return static::escapeName($this->name);
    }

    /**
     * @return string
     */
    public function renderValue()
    {
        return static::escapeValue($this->value);
    }

    /**
     * @param $name
     * @return string
     */
    public static function escapeName($name)
    {
        // TODO: escape
        return (string) $name;
    }

    /**
     * @param $value
     * @return string
     */
    public static function escapeValue($value)
    {
        // TODO: escape
        return (string) $value;
    }
}
