<?php

namespace Icinga\Module\Businessprocess\Html;

class Container extends BaseElement
{
    /** @var string */
    protected $contentSeparator = "\n";

    /** @var string */
    protected $tag = 'div';

    protected function __construct()
    {
    }

    /**
     * @param Renderable|array|string $content
     * @param Attributes|array $attributes
     * @param string $tag
     *
     * @return static
     */
    public static function create($attributes = null, $content = null, $tag = null)
    {
        $container = new static();
        if ($content !== null) {
            $container->setContent($content);
        }

        if ($attributes !== null) {
            $container->setAttributes($attributes);
        }
        if ($tag !== null) {
            $container->setTag($tag);
        }

        return $container;
    }
}

class Old {

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->renderContainerFor(parent::render());
    }

    /**
     * @inheritdoc
     */
    public function renderError($error)
    {
        // TODO: eventually add class="error"
        return $this->renderContainerFor(
            parent::renderError($error)
        );
    }

    /**
     * @param bool $render
     * @return $this
     */
    public function renderIfEmpty($render = true)
    {
        $this->renderIfEmpty = $render;
        return $this;
    }

    /**
     * @param string $content
     * @return string
     */
    protected function renderContainerFor($content)
    {
        return sprintf(
            '<%s%s>%s</%s>',
            $this->tag,
            $this->attributes->render(),
            $content,
            $this->tag
        );
    }

}