<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Exception;

class ImportedNode extends Node
{
    /** @var string */
    protected $configName;

    /** @var BpNode */
    private $node;

    protected $className = 'subtree';

    /**
     * @inheritdoc
     */
    public function __construct(BusinessProcess $bp, $object)
    {
        $this->name       = $object->name;
        $this->configName = $object->configName;
        $this->bp         = $bp;
        if (isset($object->state)) {
            $this->setState($object->state);
        } else {
            $this->setMissing();
        }
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
            $this->state = $this->importedNode()->getState();
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

    /**
     * @inheritdoc
     */
    public function isMissing()
    {
        // TODO: WHY? return $this->getState() === null;
        return $this->importedNode()->isMissing();
    }

    /**
     * @inheritdoc
     */
    public function isInDowntime()
    {
        if ($this->downtime === null) {
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
            $import = $this->storage()->loadProcess($this->configName);
            if ($this->bp->usesSoftStates()) {
                $import->useSoftStates();
            } else {
                $import->useHardStates();
            }

            $import->retrieveStatesFromBackend();

            return $import->getNode($this->name);
        } catch (Exception $e) {

            return $this->createFailedNode($e);
        }
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
        $node = new BpNode($this->bp, (object) array(
            'name'        => $this->name,
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
        return Link::create(
            $this->getAlias(),
            'businessprocess/process/show',
            array(
                'config'  => $this->configName,
                'process' => $this->name
            )
        );
    }
}
