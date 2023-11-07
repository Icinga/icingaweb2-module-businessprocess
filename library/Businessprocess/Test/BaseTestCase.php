<?php

namespace Icinga\Module\Businessprocess\Test;

use Icinga\Application\Config;
use Icinga\Application\ApplicationBootstrap;
use Icinga\Application\Icinga;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

abstract class BaseTestCase extends \Icinga\Test\BaseTestCase
{
    /** @var ApplicationBootstrap */
    private static $app;

    public function setUp(): void
    {
        parent::setUp();

        $this->getRequestMock()->shouldReceive('getBaseUrl')->andReturn('/icingaweb2/');

        $this->app()
            ->getModuleManager()
            ->loadModule('businessprocess');
    }

    protected function emptyConfigSection()
    {
        return Config::module('businessprocess')->getSection('global');
    }

    /***
     * @return BpConfig
     */
    protected function makeLoop()
    {
        return $this->makeInstance()->loadFromString(
            'loop',
            "a = b\nb = c\nc = a\nd = a"
        );
    }

    /**
     * @return LegacyStorage
     */
    protected function makeInstance()
    {
        return new LegacyStorage($this->emptyConfigSection());
    }

    /**
     * @param ?string $subDir
     * @return string
     */
    protected function getTestsBaseDir($subDir = null)
    {
        $dir = dirname(dirname(dirname(__DIR__))) . '/test';
        if ($subDir === null) {
            return $dir;
        } else {
            return $dir . '/' . ltrim($subDir, '/');
        }
    }

    /**
     * @return ApplicationBootstrap
     */
    protected function app()
    {
        if (self::$app === null) {
            self::$app = Icinga::app();
        }

        return self::$app;
    }
}
