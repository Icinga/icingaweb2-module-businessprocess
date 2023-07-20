<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Node;

/**
 * NodeRemoveAction
 *
 * Tracks removed nodes
 *
 * @package Icinga\Module\Businessprocess
 */
class NodeRemoveAction extends NodeAction
{
    protected $preserveProperties = array('parentName');

    protected $parentName;

    /**
     * @param $parentName
     * @return $this
     */
    public function setParentName($parentName = null)
    {
        $this->parentName = $parentName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * @inheritdoc
     */
    public function appliesTo(BpConfig $config)
    {
        $name = $this->getNodeName();
        $parent = $this->getParentName();
        if ($parent === null) {
            if (!$config->hasNode($name)) {
                $this->error('Toplevel process "%s" not found', $name);
            }
        } else {
            if (! $config->hasNode($parent)) {
                $this->error('Parent process "%s" missing', $parent);
            } elseif (! $config->getBpNode($parent)->hasChild($name)) {
                $this->error('Node "%s" not found in process "%s"', $name, $parent);
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BpConfig $config)
    {
        $name = $this->getNodeName();
        $parentName = $this->getParentName();
        $node = $config->getNode($name);

        $this->updateStateOverrides(
            $node,
            $parentName ? $config->getNode($parentName) : null
        );

        if ($parentName === null) {
            if (! $config->hasBpNode($name)) {
                $config->removeNode($name);
            } else {
                $oldDisplay = $config->getBpNode($name)->getDisplay();
                $config->removeNode($name);
                if ($config->getMetadata()->isManuallyOrdered()) {
                    foreach ($config->getRootNodes() as $_ => $node) {
                        $nodeDisplay = $node->getDisplay();
                        if ($nodeDisplay > $oldDisplay) {
                            $node->setDisplay($node->getDisplay() - 1);
                        } elseif ($nodeDisplay === $oldDisplay) {
                            break;  // Stop immediately to not make things worse ;)
                        }
                    }
                }
            }
        } else {
            $parent = $config->getBpNode($parentName);
            $parent->removeChild($name);
            $node->removeParent($parentName);
            if (! $node->hasParents()) {
                $config->removeNode($name);
            }
        }
    }

    /**
     * Update state overrides
     *
     * @param Node $node
     * @param BpNode|null $nodeParent
     *
     * @return void
     */
    private function updateStateOverrides(Node $node, ?BpNode $nodeParent): void
    {
        $parents = [];
        if ($nodeParent !== null) {
            $parents = [$nodeParent];
        } else {
            $parents = $node->getParents();
        }

        foreach ($parents as $parent) {
            $parentStateOverrides = $parent->getStateOverrides();
            unset($parentStateOverrides[$node->getName()]);
            $parent->setStateOverrides($parentStateOverrides);
        }
    }
}
