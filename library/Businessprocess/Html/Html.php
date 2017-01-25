<?php

namespace Icinga\Module\Businessprocess\Html;

use Icinga\Exception\ProgrammingError;

class Html implements Renderable
{
    protected $contentSeparator = '';

    /**
     * @var Renderable[]
     */
    private $content = array();

    public static function escape($any)
    {
        return Util::wantHtml($any);
    }

    /**
     * @param Renderable $element
     * @return $this
     */
    public function add(Renderable $element)
    {
        $this->content[] = $element;
        return $this;
    }

    /**
     * @param Renderable $element
     * @return $this
     */
    public function prepend(Renderable $element)
    {
        array_unshift($this->content, $element);
        return $this;
    }

    /**
     * @param Renderable|array|string $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = array(
            static::escape($content)
        );

        return $this;
    }

    /**
     * @param Renderable|array|string $content
     * @return $this
     */
    public function addContent($content)
    {
        $this->content[] = static::escape($content);
        return $this;
    }

    /**
     * @param Renderable|array|string $content
     * @return $this
     */
    public function prependContent($content)
    {
        array_unshift($this->content, static::escape($content));
        return $this;
    }

    /**
     * return Html
     */
    public function getContent()
    {
        if ($this->content === null) {
            $this->content = array(new Html());
        }

        return $this->content;
    }

    public function hasContent()
    {
        if ($this->content === null) {
            return false;
        }

        // TODO: unfinished
        // return $this->content->isEmpty();
        return true;
    }

    /**
     * @param $separator
     * @return $this
     */
    public function setSeparator($separator)
    {
        $this->contentSeparator = $separator;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        $html = array();

        foreach ($this->content as $element) {
            if (is_string($element)) {
                var_dump($this->content);
            }
            $html[] = $element->render();
        }

        return implode($this->contentSeparator, $html);
    }

    protected function translate($msg)
    {
        // TODO: Not so nice
        return mt('businessprocess', $msg);
    }

    public static function element($name, $attributes = null)
    {
        // TODO: This might be anything here, add a better check
        if (! ctype_alnum($name)) {
            throw new ProgrammingError('Invalid element requested');
        }

        $class = __NAMESPACE__ . '\\' . $name;
        /** @var Element $element */
        $element = new $class();
        if ($attributes !== null) {
            $element->setAttributes($attributes);
        }

        return $element;
    }

    /**
     * @param Exception|string $error
     * @return string
     */
    protected function renderError($error)
    {
        return Util::renderError($error);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return $this->renderError($e);
        }
    }
}
