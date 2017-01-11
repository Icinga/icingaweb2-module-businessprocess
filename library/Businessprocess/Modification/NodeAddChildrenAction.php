<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;

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

        if (! $config->hasNode($name)) {
            return false;
        }

        return $config->getNode($name) instanceof BpNode;
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BpConfig $config)
    {
        /** @var BpNode $node */
        if (! $this->hasNode()) {
            // TODO: We end up here when defining "top nodes", but that would probably
            //       be a different action
            return $this;
        }

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
