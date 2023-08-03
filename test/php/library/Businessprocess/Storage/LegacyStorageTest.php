<?php

namespace Tests\Icinga\Module\Businessprocess\Storage;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\ImportedNode;
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
                'also-with-semicolons',
                'broken_wrong-operator',
                'combined',
                'simple_with-header',
                'simple_without-header',
                'with-semicolons'
            ),
            $keys
        );
    }

    public function testHeaderTitlesAreRespectedInProcessList()
    {
        $keys = array_values($this->makeInstance()->listProcesses());
        $this->assertEquals(
            array(
                'Also With Semicolons (also-with-semicolons)',
                'broken_wrong-operator',
                'combined',
                'Simple with header (simple_with-header)',
                'simple_without-header',
                'With Semicolons (with-semicolons)'
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

    public function testConfigsWithNodesThatHaveSemicolonsInTheirNameCanBeParsed()
    {
        $bp = $this->makeInstance()->loadProcess('with-semicolons');

        $this->assertInstanceOf($this->processClass, $bp);

        $this->assertTrue($bp->hasNode('to\\;p'));
        $this->assertSame(
            'https://top.example.com/',
            $bp->getNode('to\\;p')->getInfoUrl()
        );

        $this->assertTrue($bp->hasNode('host\;1;Hoststatus'));
        $this->assertSame('host;1', $bp->getNode('host\;1;Hoststatus')->getHostname());

        $this->assertTrue($bp->hasNode('host\;1;pi;ng'));
        $this->assertSame('host;1', $bp->getNode('host\;1;pi;ng')->getHostname());
        $this->assertSame('pi;ng', $bp->getNode('host\;1;pi;ng')->getServiceDescription());

        $this->assertTrue($bp->hasNode('singleHost'));
        $this->assertTrue($bp->getNode('singleHost')->hasChild('to\\;p'));
        $this->assertInstanceOf(BpNode::class, $bp->getNode('to\\;p'));

        $this->assertInstanceOf(BpNode::class, $bp->getNode('no\\;alias'));
        $this->assertSame('no;alias', $bp->getNode('no\\;alias')->getAlias());

        $this->assertTrue($bp->hasNode('@also-with-semicolons:b\;ar'));
        $this->assertTrue($bp->getNode('singleHost')->hasChild('@also-with-semicolons:b\;ar'));
        $this->assertInstanceOf(ImportedNode::class, $bp->getNode('@also-with-semicolons:b\;ar'));
    }
}
