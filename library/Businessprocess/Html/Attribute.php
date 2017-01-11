<?php

namespace Icinga\Module\Businessprocess\Html;

class Attribute
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
     * @param string|array $value
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    /**
     * @param string $value
     * @return $this
     */
    public function addValue($value)
    {
        if (! is_array($this->value)) {
            $this->value = array($this->value);
        }

        if (is_array($value)) {
            $this->value = array_merge($this->value, $value);
        } else {
            $this->value[] = $value;
        }
        return $this;
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
        // TODO: escape differently
        if (is_array($value)) {
            return Util::escapeForHtml(implode(' ', $value));
        } else {
            return Util::escapeForHtml((string) $value);
        }
    }
}
