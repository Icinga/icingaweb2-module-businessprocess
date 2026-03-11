<?php

// SPDX-FileCopyrightText: 2018 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Web;

use Icinga\Exception\ProgrammingError;
use Icinga\Web\Request;

class FakeRequest extends Request
{
    /** @var string */
    private static $baseUrl;

    public static function setConfiguredBaseUrl($url)
    {
        self::$baseUrl = $url;
    }

    public function getBaseUrl($raw = false)
    {
        if (self::$baseUrl === null) {
            throw new ProgrammingError('Cannot determine base URL on CLI if not configured');
        } else {
            return self::$baseUrl;
        }
    }
}
