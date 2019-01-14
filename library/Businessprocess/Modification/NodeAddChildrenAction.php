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

        foreach ($this->children as $name) {
            if (! $config->hasNode($name)) {
                if (strpos($name, ';') !== false) {
                    list($host, $service) = preg_split('/;/', $name, 2);

                    if ($service === 'Hoststatus') {
                        $config->createHost($host);
                    } else {
                        $config->createService($host, $service);
                    }
                } elseif ($name[0] === '@' && strpos($name, ':') !== false) {
                    list($configName, $nodeName) = preg_split('~:\s*~', substr($name, 1), 2);
                    $config->createImportedNode($configName, $nodeName);
                }
            }
            $node->addChild($config->getNode($name));
        }

        return $this;
    }

    /**
     * @param array|string $children
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
