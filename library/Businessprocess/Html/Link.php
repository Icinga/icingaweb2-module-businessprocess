<?php

namespace Icinga\Module\Businessprocess\Html;

use Icinga\Module\Businessprocess\Web\Url;
use Icinga\Web\Url as WebUrl;

class Link extends BaseElement
{
    protected $tag = 'a';

    /** @var Url */
    protected $url;

    protected function __construct()
    {
    }

    /**
     * @param Renderable|array|string $content
     * @param Url|string $url
     * @param array $urlParams
     * @param array $attributes
     *
     * @return static
     */
    public static function create($content, $url, $urlParams = null, array $attributes = null)
    {
        $link = new static();
        $link->setContent($content);
        $link->setAttributes($attributes);
        $link->attributes()->registerCallbackFor('href', array($link, 'getHrefAttribute'));
        $link->setUrl($url, $urlParams);
        return $link;
    }

    public function setUrl($url, $urlParams)
    {
        if ($url instanceof WebUrl) { // Hint: Url is also a WebUrl
            if ($urlParams !== null) {
                $url->addParams($urlParams);
            }

            $this->url = $url;
        } else {
            if ($urlParams === null) {
                $this->url = Url::fromPath($url);
            } else {
                $this->url = Url::fromPath($url, $urlParams);
            }
        }

        $this->url->getParams();
    }

    /**
     * @return Attribute
     */
    public function getHrefAttribute()
    {
        return new Attribute('href', $this->getUrl()->getAbsoluteUrl('&'));
    }

    /**
     * @return Url
     */
    public function getUrl()
    {
        // TODO: What if null? #?
        return $this->url;
    }
}
