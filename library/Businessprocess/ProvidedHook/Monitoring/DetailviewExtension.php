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
    /**
     * @var $storage LegacyStorage
     */
    private $storage;

    /**
     * Initialize storage
     */
    public function init()
    {
        try {
            $this->storage = new LegacyStorage(
                $this->getModule()->getConfig()->getSection('global')
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
            || $object->check_command !== 'icingacli-businessprocess'
        ) {
            return '';
        }

        $bpName = $object->_service_icingacli_businessprocess_process;
        if (! $bpName) {
            return '';
        }

        $bp = $this->storage->loadProcess($bpName);

        MonitoringState::apply($bp);

        if (filter_var($object->_service_icingacli_businessprocess_grapher_tree, FILTER_VALIDATE_BOOLEAN)) {
            $renderer = new TreeRenderer($bp);
            $tag = 'ul';
        } else {
            $renderer = new TileRenderer($bp);
            $tag = 'div';
        }

        $renderer->setUrl(Url::fromPath('businessprocess/process/show?config=' . $bpName . '&node=' . $bpName));
        $renderer->ensureAssembled()->getFirst($tag)->setAttribute('data-base-target', '_next');

        return '<h2>Business Process</h2>' . $renderer;
    }
}
