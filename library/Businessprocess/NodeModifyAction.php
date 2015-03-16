<?php

namespace Icinga\Module\Businessprocess;

class NodeModifyAction extends NodeAction
{
    protected $properties = array();

    protected $formerProperties = array();

    protected $preserveProperties = array('formerProperties', 'properties');

// Can be called multiple times
    public function setNodeProperties(Node $node, $properties)
    {
        $old = array();

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

    public function appliesTo(BusinessProcess $bp)
    {
        $name = $this->getNodeName();

        if (! $bp->hasNode($name)) {
            return false;
        }

        $node = $bp->getNode($name);

        foreach ($this->properties as $key => $val) {
            $func = 'get' . ucfirst($key);
            if ($this->formerProperties[$key] !== $node->$func()) {
                return false;
            }
        }

        return true;
    }

    public function applyTo(BusinessProcess $bp)
    {
        $node = $bp->getNode($this->getNodeName());

        foreach ($this->properties as $key => $val) {
            $func = 'set' . ucfirst($key);
            $node->$func($val);
        }

        return $this;
    }

    public function setProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    public function setFormerProperties($properties)
    {
        $this->formerProperties = $properties;
        return $this;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function getFormerProperties()
    {
        return $this->formerProperties;
    }
}
