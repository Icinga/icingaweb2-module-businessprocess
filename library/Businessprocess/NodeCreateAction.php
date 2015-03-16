<?php

namespace Icinga\Module\Businessprocess;

class NodeCreateAction extends NodeAction
{
    protected $parentName;

    protected $properties = array();

    protected $preserveProperties = array('parentName', 'properties');

    public function setParent(Node $name)
    {
        $this->parentName = $name;
    }

    public function hasParent()
    {
        return $this->parentName !== null;
    }

    public function getParentName()
    {
        return $this->parentName;
    }

    public function setParentName($name)
    {
        $this->parentName = $name;
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function setProperties($properties)
    {
        $this->properties = $properties;
        return $this;
    }

    public function appliesTo(BusinessProcess $bp)
    {
        return ! $bp->hasNode($this->getNodeName());
    }

    public function applyTo(BusinessProcess $bp)
    {
        $node = new BpNode($bp, (object) array(
            'name'        => $this->getNodeName(),
            'operator'    => $this->properties->operator,
            'child_names' => $this->properties->childNames
        ));

        foreach ($this->properties as $key => $val) {
            $func = 'set' . ucfirst($key);
            $node->$func($val);
        }

        $bp->addNode($this->getNodeName(), $node);
        if ($this->hasParent()) {
            $node->addParent($bp->getNode($this->getParentName()));
        }

        return $node;
    }
}
