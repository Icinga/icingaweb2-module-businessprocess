<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Exception\IcingaException;

class Attributes extends Component
{
    /**
     * @var Attribute[]
     */
    protected $attributes;

    /**
     * Attributes constructor.
     * @param Attribute[] $attributes
     */
    public function __construct(array $attributes = null)
    {
        $this->attributes = array();
        if (empty($attributes)) {
            return;
        }

        foreach ($attributes as $key => $value) {
            if ($value instanceof Attribute) {
                $this->addAttribute($value);
            } elseif (is_string($key)) {
                $this->add($key, $value);
            } elseif (is_array($value) && count($value) === 2) {
                $this->add(array_shift($value), array_shift($value));
            }
        }
    }

    /**
     * @param Attribute[] $attributes
     * @return static
     */
    public static function create(array $attributes = null)
    {
        return new static($attributes);
    }

    /**
     * @param Attributes|array|null $attributes
     * @return static
     */
    public static function wantAttributes($attributes)
    {
        if ($attributes instanceof Attributes) {
            return $attributes;
        } else {
            $self = new static();
            if (is_array($attributes)) {
                foreach ($attributes as $k => $v) {
                    $self->add($k, $v);
                }

                return $self;

            } elseif ($attributes !== null) {
                throw new IcingaException(
                    'Attributes, Array or Null expected, got %s',
                    $self->getPhpTypeName($attributes)
                );
            }
            return $self;
        }
    }

    /**
     * @return Attribute[]
     */
    public function attributes()
    {
        return $this->attributes;
    }

    /**
     * @param Attribute|string $attribute
     * @param string|array $value
     * @return $this
     */
    public function add($attribute, $value = null)
    {
        if ($attribute instanceof static) {
            foreach ($attribute as $a) {
                $this->add($a);
            }

            return $this;
        } elseif ($attribute instanceof Attribute) {
            return $this->addAttribute($attribute);
        } else {
            return $this->addAttribute(Attribute::create($attribute, $value));
        }
    }

    /**
     * @param Attribute|string $attribute
     * @param string|array $value
     * @return $this
     */
    public function set($attribute, $value = null)
    {
        if ($attribute instanceof static) {
            foreach ($attribute as $a) {
                $this->setAttribute($a);
            }

            return $this;
        } else if ($attribute instanceof Attribute) {
            return $this->setAttribute($attribute);
        } else {
            return $this->setAttribute(new Attribute($attribute, $value));
        }
    }

    /**
     * @param Attribute $attribute
     * @return $this
     */
    public function addAttribute(Attribute $attribute)
    {
        $name = $attribute->getName();
        if (array_key_exists($name, $this->attributes)) {
            $this->attributes[$name]->addValue($attribute->getValue());
        } else {
            $this->attributes[$name] = $attribute;
        }

        return $this;
    }

    /**
     * @param Attribute $attribute
     * @return $this
     */
    public function setAttribute(Attribute $attribute)
    {
        $name = $attribute->getName();
        $this->attributes[$name] = $attribute;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        if (empty($this->attributes)) {
            return '';
        }

        return ' ' . implode(' ', $this->attributes);
    }
}