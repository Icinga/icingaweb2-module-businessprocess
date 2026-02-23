<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Web;

use Icinga\Application\Icinga;
use Icinga\Application\Web;
use Icinga\Web\Request;
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
    /**
     * @return FakeRequest|Request
     */
    protected static function getRequest()
    {
        $app = Icinga::app();
        if ($app->isCli()) {
            return new FakeRequest();
        }

        /** @var Web $app */
        return $app->getRequest();
    }
}
