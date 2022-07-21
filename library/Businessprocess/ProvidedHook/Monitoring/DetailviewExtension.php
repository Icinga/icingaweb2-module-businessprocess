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
    /** @var LegacyStorage */
    private $storage;

    /** @var string */
    private $commandName;

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

        $bpName = $object->_service_icingacli_businessprocess_config;
        if (! $bpName) {
            $bpName = key($this->storage->listProcessNames());
        }

        $nodeName = $object->_service_icingacli_businessprocess_process;
        if (! $nodeName) {
            return '';
        }

        $bp = $this->storage->loadProcess($bpName);
        $node = $bp->getBpNode($nodeName);

        MonitoringState::apply($bp);

        if (filter_var($object->_service_icingaweb_businessprocess_as_tree, FILTER_VALIDATE_BOOLEAN)) {
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
