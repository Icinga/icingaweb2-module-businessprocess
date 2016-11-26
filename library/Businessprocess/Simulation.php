<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Exception\ProgrammingError;
use Icinga\Web\Session\SessionNamespace;

class Simulation
{
    /**
     * @var SessionNamespace
     */
    protected $session;

    /**
     * @var BusinessProcess
     */
    protected $bp;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var
     */
    protected $simulations;

    public function __construct(BusinessProcess $bp, SessionNamespace $session)
    {
        $this->bp = $bp;
        $this->session = $session;
        $this->key = 'simulations.' . $bp->getName();
    }

    public function simulations()
    {
        if ($this->simulations === null) {
            $this->simulations = $this->fetchSimulations();
        }

        return $this->simulations;
    }

    protected function setSimulations($simulations)
    {
        $this->simulations = $simulations;
        $this->session->set($this->key, $simulations);
        return $this;
    }

    protected function fetchSimulations()
    {
        return $this->session->get($this->key, array());
    }

    public function clear()
    {
        $this->simulations = array();
        $this->session->set($this->key, array());
    }

    public function count()
    {
        return count($this->simulations());
    }

    public function isEmpty()
    {
        return $this->count() === 0;
    }

    public function set($node, $properties)
    {
        $simulations = $this->simulations();
        $simulations[$node] = $properties;
        $this->setSimulations($simulations);
    }

    public function hasNode($name)
    {
        $simulations = $this->simulations();
        return array_key_exists($name, $simulations);
    }

    public function getNode($name)
    {
        $simulations = $this->simulations();
        if (! array_key_exists($name, $simulations)) {
            throw new ProgrammingError('Trying to access invalid node %s', $name);
        }
        return $simulations[$name];
    }

    public function remove($node)
    {
        $simulations = $this->simulations();
        if (array_key_exists($node, $simulations)) {

            unset($simulations[$node]);
            $this->setSimulations($simulations);

            return true;
        } else {

            return false;
        }
    }
}
