<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Exception\ProgrammingError;

abstract class NodeAction
{
    const TYPE_CREATE = 'create';

    const TYPE_REMOVE = 'remove';

    const TYPE_MODIFY = 'modify';

    const TYPE_CHILD_ADD = 'childRemove';

    const TYPE_CHILD_REMOVE = 'childAdd';

    protected $nodeName;

    protected $actionName;

    protected $preserveProperties = array();

    public function __construct($node)
    {
        $this->nodeName = (string) $node;
    }

    abstract public function applyTo(BusinessProcess $bp);

    public function getNodeName()
    {
        return $this->nodeName;
    }

    public function is($actionName)
    {
        return $this->getActionName() === $actionName;
    }

    public static function create($actionName, $nodeName)
    {
        $classname = __NAMESPACE__ . '\\Node' . ucfirst($actionName) . 'Action';
        $object = new $classname($nodeName);
        return $object;
    }

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

    public static function unserialize($string)
    {
        $object = json_decode($string);
        $action = self::create($object->actionName, $object->nodeName);

        foreach ($object->properties as $key => $val) {
            $func = 'set' . ucfirst($key);
            $action->$func($val);
        }

        return $action;
    }

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
