<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;

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
        $config->calculateAllStates();
        $name = $this->getNodeName();
        $parentName = $this->getParentName();
        if ($parentName === null) {
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
        } else {
            $node = $config->getNode($name);
            $parent = $config->getBpNode($parentName);
            $parent->getState();
            $parent->removeChild($name);
            $node->removeParent($parentName);
            if (! $node->hasParents()) {
                $config->removeNode($name);
            }
        }
    }
}
