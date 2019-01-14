<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Node;

class NodeModifyAction extends NodeAction
{
    protected $properties = array();

    protected $formerProperties = array();

    protected $preserveProperties = array('formerProperties', 'properties');

    /**
     * Set properties for a specific node
     *
     * Can be called multiple times
     *
     * @param Node $node
     * @param array $properties
     *
     * @return $this
     */
    public function setNodeProperties(Node $node, array $properties)
    {
        foreach (array_keys($properties) as $key) {
            $this->properties[$key] = $properties[$key];

            if (array_key_exists($key, $this->formerProperties)) {
                continue;
            }

            $func = 'get' . ucfirst($key);
            $this->formerProperties[$key] = $node->$func();
        }

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function appliesTo(BpConfig $config)
    {
        $name = $this->getNodeName();

        if (! $config->hasNode($name)) {
            $this->error('Node "%s" not found', $name);
        }

        $node = $config->getNode($name);

        foreach ($this->properties as $key => $val) {
            $currentVal = $node->{'get' . ucfirst($key)}();
            if ($this->formerProperties[$key] !== $currentVal) {
                $this->error(
                    'Property %s of node "%s" changed its value from "%s" to "%s"',
                    $key,
                    $name,
                    $this->formerProperties[$key],
                    $currentVal
                );
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BpConfig $config)
    {
        $node = $config->getNode($this->getNodeName());

        foreach ($this->properties as $key => $val) {
            $func = 'set' . ucfirst($key);
            $node->$func($val);
        }

        return $this;
    }

    /**
     * @param $properties
     * @return $this
     */
    public function setProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * @param $properties
     * @return $this
     */
    public function setFormerProperties($properties)
    {
        $this->formerProperties = $properties;
        return $this;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @return array
     */
    public function getFormerProperties()
    {
        return $this->formerProperties;
    }
}
