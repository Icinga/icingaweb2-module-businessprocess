<?php

namespace Icinga\Module\Businessprocess\Html;

class Text implements Renderable
{
    /** @var string */
    protected $string;

    protected $escaped = false;

    /**
     * Text constructor.
     *
     * @param $text
     */
    public function __construct($string)
    {
        $this->string = (string) $string;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->string;
    }

    /**
     * @param bool $escaped
     * @return $this
     */
    public function setEscaped($escaped = true)
    {
        $this->escaped = $escaped;
        return $this;
    }

    /**
     * @param $text
     *
     * @return static
     */
    public static function create($text)
    {
        return new static($text);
    }

    /**
     * @return string
     */
    public function render()
    {
        if ($this->escaped) {
            return $this->string;
        } else {
            return Util::escapeForHtml($this->string);
        }
    }
}
