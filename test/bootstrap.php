<?php

use Icinga\Module\Businessprocess\Test\Bootstrap;

call_user_func(function () {
    $basedir = dirname(__DIR__);
    require_once $basedir . '/library/Businessprocess/Test/Bootstrap.php';
    Bootstrap::cli($basedir);
});
