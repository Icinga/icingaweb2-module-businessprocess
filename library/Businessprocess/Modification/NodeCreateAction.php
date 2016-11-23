<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Node;
use stdClass;

class NodeCreateAction extends NodeAction
{
    /** @var string */
    protected $parentName;

    /** @var array */
    protected $properties = array();

    /** @var array */
    protected $preserveProperties = array('parentName', 'properties');

    /**
     * @param Node $name
     */
    public function setParent(Node $name)
    {
        $this->parentName = (string) $name;
    }

    /**
     * @return bool
     */
    public function hasParent()
    {
        return $this->parentName !== null;
    }

    /**
     * @return string
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    /**
     * @param string $name
     */
    public function setParentName($name)
    {
        $this->parentName = $name;
    }

    /**
     * @return array
     */
    public function getProperties()
    {
        return $this->properties;
    }

    /**
     * @param stdClass $properties
     * @return $this
     */
    public function setProperties(stdClass $properties)
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * @inheritdoc
     */
    public function appliesTo(BusinessProcess $bp)
    {
        return ! $bp->hasNode($this->getNodeName());
    }

    /**
     * @inheritdoc
     */
    public function applyTo(BusinessProcess $bp)
    {
        $name = $this->getNodeName();

        $node = new BpNode($bp, (object) array(
            'name'        => $name,
            'operator'    => $this->properties['operator'],
            'child_names' => $this->properties['childNames']
        ));

        foreach ($this->getProperties() as $key => $val) {
            $func = 'set' . ucfirst($key);
            $node->$func($val);
        }

        $bp->addNode($name, $node);
        if ($this->hasParent()) {
            $node->addParent($bp->getNode($this->getParentName()));
        }

        return $node;
    }
}
