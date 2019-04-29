<?php

namespace Icinga\Module\Businessprocess;

use Exception;

class ImportedNode extends BpNode
{
    /** @var BpConfig */
    protected $parentBp;

    /** @var string */
    protected $configName;

    /** @var string */
    protected $nodeName;

    /** @var BpNode */
    protected $importedNode;

    /** @var string */
    protected $className = 'process subtree';

    /** @var string */
    protected $icon = 'download';

    public function __construct(BpConfig $parentBp, $object)
    {
        $this->parentBp = $parentBp;
        $this->configName = $object->configName;
        $this->nodeName = $object->node;

        parent::__construct((object) [
            'name'          => '@' . $this->configName . ':' . $this->nodeName,
            'operator'      => null,
            'child_names'   => null
        ]);
    }

    /**
     * @return string
     */
    public function getConfigName()
    {
        return $this->configName;
    }

    /**
     * @return string
     */
    public function getNodeName()
    {
        return $this->nodeName;
    }

    public function getIdentifier()
    {
        return $this->getName();
    }

    public function getBpConfig()
    {
        if ($this->bp === null) {
            $this->bp = $this->parentBp->getImportedConfig($this->configName);
        }

        return $this->bp;
    }

    public function getAlias()
    {
        if ($this->alias === null) {
            $this->alias = $this->importedNode()->getAlias();
        }

        return $this->alias;
    }

    public function getOperator()
    {
        if ($this->operator === null) {
            $this->operator = $this->importedNode()->getOperator();
        }

        return $this->operator;
    }

    public function getChildNames()
    {
        if ($this->childNames === null) {
            $this->childNames = $this->importedNode()->getChildNames();
        }

        return $this->childNames;
    }

    /**
     * @return BpNode
     */
    protected function importedNode()
    {
        if ($this->importedNode === null) {
            try {
                $this->importedNode = $this->getBpConfig()->getBpNode($this->nodeName);
            } catch (Exception $e) {
                return $this->createFailedNode($e);
            }
        }

        return $this->importedNode;
    }

    /**
     * @param Exception $e
     *
     * @return BpNode
     */
    protected function createFailedNode(Exception $e)
    {
        $this->parentBp->addError("%s", $e->getMessage());
        $node = new BpNode((object) array(
            'name'        => $this->getName(),
            'operator'    => '&',
            'child_names' => []
        ));
        $node->setBpConfig($this->getBpConfig());
        $node->setState(2);
        $node->setMissing(false)
            ->setDowntime(false)
            ->setAck(false)
            ->setAlias($e->getMessage());

        return $node;
    }
}
