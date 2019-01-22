<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Exception;
use Icinga\Web\Url;
use ipl\Html\Html;

class ImportedNode extends Node
{
    /** @var string */
    protected $configName;

    /** @var string */
    protected $nodeName;

    /** @var BpNode */
    private $node;

    protected $className = 'subtree';

    protected $icon = 'download';

    /** @var BpConfig */
    private $config;

    /**
     * @inheritdoc
     */
    public function __construct(BpConfig $bp, $object)
    {
        $this->bp = $bp;
        $this->configName = $object->configName;
        $this->name = '@' . $object->configName;
        if (property_exists($object, 'node')) {
            $this->nodeName = $object->node;
            $this->name .= ':' . $object->node;
        } else {
            $this->useAllRootNodes();
        }

        if (isset($object->state)) {
            $this->setState($object->state);
        }
    }

    public function hasNode()
    {
        return $this->nodeName !== null;
    }

    /**
     * @return string
     */
    public function getConfigName()
    {
        return $this->configName;
    }

    /**
     * @inheritdoc
     */
    public function getState()
    {
        if ($this->state === null) {
            try {
                MonitoringState::apply($this->importedConfig());
            } catch (Exception $e) {
            }

            $this->state = $this->importedNode()->getState();
            $this->setMissing(false);
        }

        return $this->state;
    }

    /**
     * @inheritdoc
     */
    public function getAlias()
    {
        return $this->importedNode()->getAlias();
    }

    public function getUrl()
    {
        $params = array(
            'config'    => $this->getConfigName(),
            'node' => $this->importedNode()->getName()
        );

        return Url::fromPath('businessprocess/process/show', $params);
    }

    /**
     * @inheritdoc
     */
    public function isMissing()
    {
        $this->getState();
        // Probably doesn't work, as we create a fake node with worse state
        return $this->missing;
    }

    /**
     * @inheritdoc
     */
    public function isInDowntime()
    {
        if ($this->downtime === null) {
            $this->getState();
            $this->downtime = $this->importedNode()->isInDowntime();
        }

        return $this->downtime;
    }

    /**
     * @inheritdoc
     */
    public function isAcknowledged()
    {
        if ($this->ack === null) {
            $this->getState();
            $this->downtime = $this->importedNode()->isAcknowledged();
        }

        return $this->ack;
    }

    /**
     * @return BpNode
     */
    protected function importedNode()
    {
        if ($this->node === null) {
            $this->node = $this->loadImportedNode();
        }

        return $this->node;
    }

    /**
     * @return BpNode
     */
    protected function loadImportedNode()
    {
        try {
            $import = $this->importedConfig();

            return $import->getNode($this->nodeName);
        } catch (Exception $e) {
            return $this->createFailedNode($e);
        }
    }

    protected function useAllRootNodes()
    {
        try {
            $bp = $this->importedConfig();
            $this->node = new BpNode($bp, (object) array(
                'name'        => $this->getName(),
                'operator'    => '&',
                'child_names' => $bp->listRootNodes(),
            ));
        } catch (Exception $e) {
            $this->createFailedNode($e);
        }
    }

    /**
     * @return BpConfig
     */
    protected function importedConfig()
    {
        if ($this->config === null) {
            $import = $this->storage()->loadProcess($this->configName);
            if ($this->bp->usesSoftStates()) {
                $import->useSoftStates();
            } else {
                $import->useHardStates();
            }

            $this->config = $import;
        }

        return $this->config;
    }

    /**
     * @return LegacyStorage
     */
    protected function storage()
    {
        return new LegacyStorage(
            Config::module('businessprocess')->getSection('global')
        );
    }

    /**
     * @param Exception $e
     *
     * @return BpNode
     */
    protected function createFailedNode(Exception $e)
    {
        $this->bp->addError($e->getMessage());
        $node = new BpNode($this->importedConfig(), (object) array(
            'name'        => $this->getName(),
            'operator'    => '&',
            'child_names' => array()
        ));
        $node->setState(2);
        $node->setMissing(false)
            ->setDowntime(false)
            ->setAck(false)
            ->setAlias($e->getMessage());

        return $node;
    }

    /**
     * @inheritdoc
     */
    public function getLink()
    {
        return Html::tag(
            'a',
            [
                'href'  => Url::fromPath('businessprocess/process/show', [
                    'config'    => $this->configName,
                    'node'      => $this->nodeName
                ])
            ],
            $this->getAlias()
        );
    }
}
