<?php

namespace Tests\Icinga\Module\Businessprocess\Storage;

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Storage\Storage;

class LegacyStorageTest extends BaseTestCase
{
    public function testWhetherItCanBeInstantiatedWithAnEmptyConfigSection()
    {
        $baseClass = 'Icinga\\Module\\Businessprocess\\Storage\\LegacyStorage';
        $this->assertInstanceOf(
            $baseClass,
            new LegacyStorage(Config::module('businessprocess')->getSection('global'))
        );
    }

    public function testWhetherDefaultConfigDirIsDetermined()
    {
        $this->assertEquals(
            $this->getTestsBaseDir('config/modules/businessprocess/processes'),
            $this->makeInstance()->getConfigDir()
        );
    }

    public function testWhetherAllProcessesAreListed()
    {
        $keys = array_keys($this->makeInstance()->listProcesses());
        sort($keys);
        $this->assertEquals(
            $keys,
            array(
                'broken_wrong-operator',
                'simple_with-header',
                'simple_without-header',
            )
        );
    }

    public function testWhetherHeadersAreRespectedInProcessList()
    {
        $keys = array_values($this->makeInstance()->listProcesses());
        sort($keys);
        $this->assertEquals(
            $keys,
            array(
                'Simple with header (simple_with-header)',
                'broken_wrong-operator',
                'simple_without-header',
            )
        );
    }

    public function testWhetherProcessFilenameIsReturned()
    {
        $this->assertEquals(
            $this->getTestsBaseDir('config/modules/businessprocess/processes/simple_with-header.conf'),
            $this->makeInstance()->getFilename('simple_with-header')
        );
    }

    public function testWhetherExistingProcessExists()
    {
        $this->assertTrue(
            $this->makeInstance()->hasProcess('simple_with-header')
        );
    }

    public function testWhetherMissingProcessIsMissing()
    {
        $this->assertFalse(
            $this->makeInstance()->hasProcess('simple_with-headerx')
        );
    }

    public function testWhetherValidProcessCanBeLoaded()
    {
        $processClass = 'Icinga\\Module\\Businessprocess\\BusinessProcess';
        $this->assertInstanceOf(
            $processClass,
            $this->makeInstance()->loadProcess('simple_with-header')
        );
    }

    public function testWhetherConfigCanBeLoadedFromAString()
    {
        $processClass = 'Icinga\\Module\\Businessprocess\\BusinessProcess';
        $this->assertInstanceOf(
            $processClass,
            $this->makeInstance()->loadFromString('dummy', 'a = Host1;ping & Host2;ping')
        );
    }

    public function testWhetherProcessSourceCanBeFetched()
    {
        $this->assertEquals(
            file_get_contents($this->getTestsBaseDir('config/modules/businessprocess/processes/simple_with-header.conf')),
            $this->makeInstance()->getSource('simple_with-header')
        );
    }

    /**
     * @return LegacyStorage
     */
    protected function makeInstance()
    {
        return new LegacyStorage(Config::module('businessprocess')->getSection('global'));
    }
}