<?php

namespace Icinga\Module\Businessprocess\Modification;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Exception\ModificationError;
use Icinga\Module\Businessprocess\Node;
use Icinga\Exception\ProgrammingError;

/**
 * Abstract NodeAction class
 *
 * Every instance of a NodeAction represents a single applied change. Changes are pushed to
 * a stack and consumed from there. When persisted, NodeActions are serialized with their name,
 * node name and optionally additional properties according preserveProperties. For each property
 * that should be preserved, getter and setter methods have to be defined.
 *
 * @package Icinga\Module\Businessprocess
 */
abstract class NodeAction
{
    /** @var string Name of this action (currently create, modify, remove) */
    protected $actionName;

    /** @var string Name of the node this action applies to */
    protected $nodeName;

    /** @var array Properties which should be preserved when serializing this action */
    protected $preserveProperties = array();

    /**
     * NodeAction constructor.
     *
     * @param Node|string $node
     */
    public function __construct($node = null)
    {
        if ($node !== null) {
            $this->nodeName = (string) $node;
        }
    }

    /**
     * Every NodeAction must be able to apply itself to a BusinessProcess
     *
     * @param BpConfig $config
     * @return mixed
     */
    abstract public function applyTo(BpConfig $config);

    /**
     * Every NodeAction must be able to tell whether it can be applied to a BusinessProcess
     *
     * @param   BpConfig    $config
     *
     * @throws  ModificationError
     *
     * @return  bool
     */
    abstract public function appliesTo(BpConfig $config);

    /**
     * The name of the node this modification applies to
     *
     * @return string
     */
    public function getNodeName()
    {
        return $this->nodeName;
    }

    public function hasNode()
    {
        return $this->nodeName !== null;
    }

    /**
     * Whether this is an instance of a given action name
     *
     * @param string $actionName
     * @return bool
     */
    public function is($actionName)
    {
        return $this->getActionName() === $actionName;
    }

    /**
     * Throw a ModificationError
     *
     * @param   string  $msg
     * @param   mixed   ...
     *
     * @throws  ModificationError
     */
    protected function error($msg)
    {
        $error = ModificationError::create(func_get_args());
        /** @var ModificationError $error */
        throw $error;
    }

    /**
     * Create an instance of a given actionName for a specific Node
     *
     * @param string $actionName
     * @param string $nodeName
     *
     * @return static
     */
    public static function create($actionName, $nodeName)
    {
        $className = __NAMESPACE__ . '\\Node' . ucfirst($actionName) . 'Action';
        $object = new $className($nodeName);
        return $object;
    }

    /**
     * Returns a JSON-encoded serialized NodeAction
     *
     * @return string
     */
    public function serialize()
    {
        $object = (object) array(
            'actionName' => $this->getActionName(),
            'nodeName'   => $this->getNodeName(),
            'properties' => array()
        );

        foreach ($this->preserveProperties as $key) {
            $func = 'get' . ucfirst($key);
            $object->properties[$key] = $this->$func();
        }

        return json_encode($object);
    }

    /**
     * Decodes a JSON-serialized NodeAction and returns an object instance
     *
     * @param $string
     * @return NodeAction
     */
    public static function unSerialize($string)
    {
        $object = json_decode($string);
        $action = self::create($object->actionName, $object->nodeName);

        foreach ($object->properties as $key => $val) {
            $func = 'set' . ucfirst($key);
            $action->$func($val);
        }

        return $action;
    }

    /**
     * Returns the defined action name or determines such from the class name
     *
     * @return string The action name
     *
     * @throws ProgrammingError when no such class exists
     */
    public function getActionName()
    {
        if ($this->actionName === null) {
            if (! preg_match('/\\\Node(\w+)Action$/', get_class($this), $m)) {
                throw new ProgrammingError(
                    '"%s" is not a NodeAction class',
                    get_class($this)
                );
            }
            $this->actionName = lcfirst($m[1]);
        }

        return $this->actionName;
    }
}
