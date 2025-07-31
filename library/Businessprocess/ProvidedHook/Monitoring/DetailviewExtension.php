<?php

namespace Icinga\Module\Businessprocess\ProvidedHook\Monitoring;

use Icinga\Module\Businessprocess\Renderer\TileRenderer;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Web\Url;
use Icinga\Module\Monitoring\Hook\DetailviewExtensionHook;
use Icinga\Module\Monitoring\Object\MonitoredObject;
use Icinga\Module\Monitoring\Object\Service;

class DetailviewExtension extends DetailviewExtensionHook
{
    /** @var ?LegacyStorage */
    private $storage;

    /** @var string */
    private $commandName;

    /** @var string */
    private $configVar;

    /** @var string */
    private $processVar;

    /** @var string */
    private $treeVar;

    /**
     * Initialize storage
     */
    public function init()
    {
        try {
            $this->storage = LegacyStorage::getInstance();
            $this->commandName = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'checkcommand_name',
                'icingacli-businessprocess'
            );
            $this->configVar = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'config_var',
                'icingacli_businessprocess_config'
            );
            $this->processVar = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'process_var',
                'icingacli_businessprocess_process'
            );
            $this->treeVar = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'tree_var',
                'icingaweb_businessprocess_as_tree'
            );
        } catch (\Exception $e) {
            // Ignore and don't display anything
        }
    }

    /**
     * Returns the rendered Tree-/TileRenderer HTML
     *
     * @param MonitoredObject $object
     *
     * @return string
     */
    public function getHtmlForObject(MonitoredObject $object)
    {
        if (! isset($this->storage)
            || ! $object instanceof Service
            || $object->check_command !== $this->commandName
        ) {
            return '';
        }

        $customvars = array_merge($object->fetchHostVariables()->hostVariables, $object->fetchCustomvars()->customvars);  #Must grab customvars with this method, object access will not work with coalesce operator

        $bpName = $customvars[$this->configVar] ?? null; 
        if (! $bpName) {
            $bpName = key($this->storage->listProcessNames());
        }

        $nodeName = $customvars[$this->processVar] ?? null;
        if (! $nodeName) {
            return '';
        }

        $bp = $this->storage->loadProcess($bpName);
        $node = $bp->getBpNode($nodeName);

        MonitoringState::apply($bp);

        if (filter_var( $customvars[$this->treeVar] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $renderer = new TreeRenderer($bp, $node);
            $tag = 'ul';
        } else {
            $renderer = new TileRenderer($bp, $node);
            $tag = 'div';
        }

        $renderer->setUrl(Url::fromPath('businessprocess/process/show?config=' . $bpName . '&node=' . $nodeName));
        $renderer->ensureAssembled()->getFirst($tag)->setAttribute('data-base-target', '_next');

        return '<h2>Business Process</h2>' . $renderer;
    }
}
