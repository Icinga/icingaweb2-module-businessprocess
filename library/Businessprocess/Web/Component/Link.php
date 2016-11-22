<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Web\Url;

class Link extends Component
{
    /** @var string TODO: mixed, allow components */
    protected $text;

    /** @var Url */
    protected $url;

    /** @var Attributes */
    protected $attributes;

    protected function __construct()
    {
    }

    /**
     * @param Component|string $text
     * @param Url|string $url
     * @param array $urlParams
     * @param array $attributes
     *
     * @return static
     */
    public static function create($text, $url, $urlParams = null, array $attributes = null)
    {
        $link = new static();
        $link->text = $text;
        if ($url instanceof Url) {
            if ($urlParams !== null) {
                $url->addParams($urlParams);
            }

            $link->url = $url;
        } else {
            $link->url = Url::fromPath($url, $urlParams);
        }
        $link->attributes = new Attributes($attributes);

        return $link;
    }

    /**
     * @return string
     */
    public function getRenderedText()
    {
        return $this->view()->escape((string) $this->text);
    }

    /**
     * @return Url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * @inheritdoc
     */
    public function render()
    {
        return sprintf(
            '<a href="%s"%s>%s</a>',
            $this->getUrl(),
            $this->attributes->render(),
            $this->getRenderedText()
        );
    }
}