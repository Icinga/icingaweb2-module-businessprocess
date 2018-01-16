<?php

namespace Icinga\Module\Businessprocess\Web;

use Icinga\Application\Icinga;
use Icinga\Web\Url as WebUrl;

/**
 * Class Url
 *
 * The main purpose of this class is to get unit tests running on CLI
 *
 * @package Icinga\Module\Businessprocess\Web
 */
class Url extends WebUrl
{
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
