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

        foreach ($this->children as $name => $properties) {
            if (is_int($name)) {
                // TODO: This may be removed once host nodes also have properties
                $name = $properties;
            }

            if (! $config->hasNode($name) || $config->getNode($name)->getBpConfig()->getName() !== $config->getName()) {
                if (strpos($name, ';') !== false) {
                    list($host, $service) = preg_split('/;/', $name, 2);

                    if ($service === 'Hoststatus') {
                        $child = $config->createHost($host);
                    } else {
                        $child = $config->createService($host, $service);
                    }

                    if (is_array($properties) && !empty($properties)) {
                        foreach ($properties as $key => $value) {
                            $func = 'set' . ucfirst($key);
                            $child->$func($value);
                        }
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
