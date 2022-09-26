<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;

class NodeAddChildrenAction extends NodeAction
{
    protected $children = array();

    protected $preserveProperties = array('children');

    /**
     * @inheritdoc
     */
    public function appliesTo(BpConfig $config)
    {
        $name = $this->getNodeName();

        if (! $config->hasBpNode($name)) {
            $this->error('Process "%s" not found', $name);
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BpConfig $config)
    {
        $node = $config->getBpNode($this->getNodeName());

        foreach ($this->children as $child) {
            if ( ! $config->hasNode($child['id']) || $config->getNode($child['id'])->getBpConfig()->getName() !== $config->getName()) {
                if ($child['type'] === 'host' || $child['type'] === 'service') {
                    if ($child['type'] === 'host') {
                        $config->createHost($child['hostname']);
                    } else {
                        $config->createService($child['hostname'], $child['servicename']);
                    }
                } elseif ($child[0] === '@' && strpos($child, ':') !== false) {
                    list($configName, $nodeName) = preg_split('~:\s*~', substr($child, 1), 2);
                    $config->createImportedNode($configName, $nodeName);
                }
            }
            $node->addChild($config->getNode($child['id']));
        }

        return $this;
    }

    /**
     * @param array $children
     *
     * @return $this
     */
    public function setChildren($children)
    {
        if (is_string($children)) {
            $children = array($children);
        }
        $this->children = $children;
        return $this;
    }

    /**
     * @return array
     */
    public function getChildren()
    {
        return $this->children;
    }
}
