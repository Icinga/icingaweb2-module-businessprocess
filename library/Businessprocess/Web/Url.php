<?php

namespace Icinga\Module\Businessprocess\Web;

use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use Icinga\Web\Url as WebUrl;

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
        
        if (isset($parts['fragment'])) {
            $self->setAnchor($parts['fragment']);
        }

        $self->setParams($params);
        return $self;
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