<?php

namespace Icinga\Module\Businessprocess\Web;

use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use Icinga\Web\Url as WebUrl;
use Icinga\Web\UrlParams;

/**
 * Class Url
 *
 * The main purpose of this class is to get unit tests running on CLI
 * Little code from Icinga\Web\Url has been duplicated, as neither fromPath()
 * nor getRequest() can be extended in a meaningful way at the time of this
 * writing
 *
 * @package Icinga\Module\Businessprocess\Web
 */
class Url extends WebUrl
{
    public static function fromPath($url, array $params = array(), $request = null)
    {
        if ($request === null) {
            $request = static::getRequest();
        }

        if (! is_string($url)) {
            throw new ProgrammingError(
                'url "%s" is not a string',
                $url
            );
        }

        $self = new static;

        if ($url === '#') {
            return $self->setPath($url);
        }

        $parts = parse_url($url);

        $self->setBasePath($request->getBaseUrl());
        if (isset($parts['path'])) {
            $self->setPath($parts['path']);
        }

        if (isset($urlParts['query'])) {
            $params = UrlParams::fromQueryString($urlParts['query'])->mergeValues($params);
        }

        if (isset($parts['fragment'])) {
            $self->setAnchor($parts['fragment']);
        }

        $self->setParams($params);
        return $self;
    }

    public function setBasePath($basePath)
    {
        if (property_exists($this, 'basePath')) {
            parent::setBasePath($basePath);
        } else {
            return $this->setBaseUrl($basePath);
        }
    }

    protected static function getRequest()
    {
        $app = Icinga::app();
        if ($app->isCli()) {
            return new FakeRequest();
        } else {
            return $app->getRequest();
        }
    }
}
