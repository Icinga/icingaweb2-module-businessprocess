<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Application\Config;
use Icinga\Web\Url;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Exception;

class ImportedNode extends Node
{
    protected $configName;

    protected $importedBp;

    protected $importedNode;

    protected $className = 'subtree';

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

    public function getConfigName()
    {
        return $this->configName;
    }

    public function getState()
    {
        if ($this->state === null) {
            $this->state = $this->importedNode()->getState();
        }
        return $this->state;
    }

    public function getAlias()
    {
        return $this->importedNode()->getAlias();
    }

    public function isMissing()
    {
        return $this->importedNode()->isMissing();
        // TODO: WHY? return $this->getState() === null;
    }

    public function isInDowntime()
    {
        if ($this->downtime === null) {
            $this->downtime = $this->importedNode()->isInDowntime();
        }
        return $this->downtime;
    }

    public function isAcknowledged()
    {
        if ($this->ack === null) {
            $this->downtime = $this->importedNode()->isAcknowledged();
        }
        return $this->ack;
    }

    protected function importedNode()
    {
        if ($this->importedNode === null) {
            $storage = new LegacyStorage(
                Config::module('businessprocess')->getSection('global')
            );
            try {
                $this->importedBp = $storage->loadProcess($this->configName);
                if ($this->bp->usesSoftStates()) {
                    $this->importedBp->useSoftStates();
                } else {
                    $this->importedBp->useHardStates();
                }
                $this->importedBp->retrieveStatesFromBackend();
                $this->importedNode = $this->importedBp->getNode($this->name);
            } catch (Exception $e) {


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

                $this->importedNode = $node;
            }
        }
        return $this->importedNode;
    }
    
    protected function getActionIcons($view)
    {
        $icons = array();

        if (! $this->bp->isLocked()) {

            $url = Url::fromPath( 'businessprocess/node/simulate', array(
                'config' => $this->bp->getName(),
                'node' => $this->name
            ));

            $icons[] = $this->actionIcon(
                $view,
                'magic',
                $url,
                'Simulation'
            );
        }

        return $icons;
    }

    public function renderLink($view)
    {
        return $view->qlink($this->getAlias(), 'businessprocess/process/show', array(
            'config'  => $this->configName,
            'process' => $this->name
        ));
    }
}
