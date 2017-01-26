<?php

namespace Tests\Icinga\Module\Businessprocess\Storage;

use Icinga\Module\Businessprocess\Test\BaseTestCase;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;

class LegacyStorageTest extends BaseTestCase
{
    private $processClass = 'Icinga\\Module\\Businessprocess\\BpConfig';

    public function testCanBeInstantiatedWithAnEmptyConfigSection()
    {
        $baseClass = 'Icinga\\Module\\Businessprocess\\Storage\\LegacyStorage';
        $this->assertInstanceOf(
            $baseClass,
            new LegacyStorage($this->emptyConfigSection())
        );
    }

    public function testDefaultConfigDirIsDiscoveredCorrectly()
    {
        $this->assertEquals(
            $this->getTestsBaseDir('config/modules/businessprocess/processes'),
            $this->makeInstance()->getConfigDir()
        );
    }

    public function testAllAvailableProcessesAreListed()
    {
        $keys = array_keys($this->makeInstance()->listProcesses());
        $this->assertEquals(
            array(
                'broken_wrong-operator',
                'combined',
                'simple_with-header',
                'simple_without-header',
            ),
            $keys
        );
    }

    public function testHeaderTitlesAreRespectedInProcessList()
    {
        $keys = array_values($this->makeInstance()->listProcesses());
        $this->assertEquals(
            array(
                'broken_wrong-operator',
                'combined',
                'Simple with header (simple_with-header)',
                'simple_without-header',
            ),
            $keys
        );
    }

    public function testProcessFilenameIsReturned()
    {
        $this->assertEquals(
            $this->getTestsBaseDir('config/modules/businessprocess/processes/simple_with-header.conf'),
            $this->makeInstance()->getFilename('simple_with-header')
        );
    }

    public function testAnExistingProcessExists()
    {
        $this->assertTrue(
            $this->makeInstance()->hasProcess('simple_with-header')
        );
    }

    public function testAMissingProcessIsMissing()
    {
        $this->assertFalse(
            $this->makeInstance()->hasProcess('simple_with-headerx')
        );
    }

    public function testAValidProcessCanBeLoaded()
    {
        $this->assertInstanceOf(
            $this->processClass,
            $this->makeInstance()->loadProcess('simple_with-header')
        );
    }

    public function testProcessConfigCanBeLoadedFromAString()
    {
        $this->assertInstanceOf(
            $this->processClass,
            $this->makeInstance()->loadFromString('dummy', 'a = Host1;ping & Host2;ping')
        );
    }

    public function testFullProcessSourceCanBeFetched()
    {
        $this->assertEquals(
            file_get_contents(
                $this->getTestsBaseDir(
                    'config/modules/businessprocess/processes/simple_with-header.conf'
                )
            ),
            $this->makeInstance()->getSource('simple_with-header')
        );
    }

    public function testTitleCanBeReadFromConfig()
    {
        $this->assertEquals(
            'Simple with header',
            $this->makeInstance()->loadProcess('simple_with-header')->getMetadata()->get('Title')
        );
    }

    public function testInfoUrlBeReadFromConfig()
    {
        $this->assertEquals(
            'https://top.example.com/',
            $this->makeInstance()->loadProcess('simple_with-header')->getBpNode('top')->getInfoUrl()
        );
    }

    public function testAConfiguredLoopCanBeParsed()
    {
        $this->assertInstanceOf(
            $this->processClass,
            $this->makeLoop()
        );
    }

    public function testImportedNodesCanBeParsed()
    {
        $this->assertInstanceOf(
            $this->processClass,
            $this->makeInstance()->loadProcess('combined')
        );
    }
}
