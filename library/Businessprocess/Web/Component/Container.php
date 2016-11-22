<?php

namespace Icinga\Module\Businessprocess\Web\Component;

class Container extends Component
{
    /**
     * @var array
     */
    protected $content = array();

    /** @var Attributes|array Is never an array at runtime, but may be before __construction */
    protected $attributes;

    /** @var string */
    protected $separator = "\n";

    /** @var string */
    protected $tag = 'div';

    /**
     * Container constructor.
     * @param Component|array|string $content
     * @param Attributes|array $attributes
     * @param string $tag
     */
    protected function __construct($content = array(), $attributes = null, $tag = null)
    {
        $this->addContent($content);

        if ($this->attributes === null) {
            $this->attributes = Attributes::wantAttributes($attributes);
        } else {
            $this->attributes = Attributes::wantAttributes($this->attributes);
        }
        if ($tag !== null) {
            $this->tag = $tag;
        }
    }

    /**
     * @param Component|array|string $content
     * @param Attributes|array $attributes
     * @param string $tag
     *
     * @return static
     */
    public static function create($content = array(), $attributes = null, $tag = null)
    {
        return new static($content, $attributes, $tag);
    }

    /**
     * @return Attributes
     */
    public function attributes()
    {
        return $this->attributes;
    }

    /**
     * @see addContent()
     * @param Component|array|string $content
     * @return $this
     */
    public function add($content)
    {
        return $this->addContent($content);
    }

    /**
     * @param Component|array|string $content
     * @return $this
     */
    public function addContent($content)
    {
        if (is_array($content)) {
            foreach ($content as $c) {
                $this->addContent($c);
            }
        }

        $htmlOrComponent = $this->wantHtml($content);
        if (strlen($htmlOrComponent)) {
            $this->content[] = $htmlOrComponent;
        }

        return $this;
    }

    /**
     * @param Component|array|string $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = array();
        $this->addContent($content);

        return $this;
    }

    /**
     * @return string
     */
    public function renderContent()
    {
        return implode($this->separator, $this->content);
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return $this->renderContainerFor($this->renderContent());
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
     * @param $content
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