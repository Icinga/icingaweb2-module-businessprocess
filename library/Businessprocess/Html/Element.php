<?php

namespace Icinga\Module\Businessprocess\Html;

class Element extends BaseElement
{
    /**
     * Container constructor.
     *
     * @param string $tag
     * @param Attributes|array $attributes
     */
    public function __construct($tag, $attributes = null)
    {
        $this->tag = $tag;

        if ($attributes !== null) {
            $this->attributes = $this->attributes()->add($attributes);
        }
    }

    /**
     * Container constructor.
     *
     * @param string $tag
     * @param Attributes|array $attributes
     *
     * @return static
     */
    public static function create($tag, $attributes = null)
    {
        return new static($tag, $attributes);
    }
}
