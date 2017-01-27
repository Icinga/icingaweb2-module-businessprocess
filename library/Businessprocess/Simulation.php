<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Exception\ProgrammingError;
use Icinga\Web\Session\SessionNamespace;

class Simulation
{
    const DEFAULT_SESSION_KEY = 'bp-simulations';

    /**
     * @var SessionNamespace
     */
    protected $session;

    /**
     * @var string
     */
    protected $sessionKey;

    /**
     * @var array
     */
    protected $simulations = array();

    /**
     * Simulation constructor.
     * @param array $simulations
     */
    public function __construct(array $simulations = array())
    {
        $this->simulations = $simulations;
    }

    /**
     * @param array $simulations
     * @return static
     */
    public static function create(array $simulations = array())
    {
        return new static($simulations);
    }

    /**
     * @param SessionNamespace $session
     * @param null $sessionKey
     * @return $this
     */
    public static function fromSession(SessionNamespace $session, $sessionKey = null)
    {
        return static::create()
            ->setSessionKey($sessionKey)
            ->persistToSession($session);
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setSessionKey($key = null)
    {
        if ($key === null) {
            $this->sessionKey = Simulation::DEFAULT_SESSION_KEY;
        } else {
            $this->sessionKey = $key;
        }

        return $this;
    }

    /**
     * @param SessionNamespace $session
     * @return $this
     */
    public function persistToSession(SessionNamespace $session)
    {
        $this->session = $session;
        $this->simulations = $this->session->get($this->sessionKey, array());
        return $this;
    }

    /**
     * @return array
     */
    public function simulations()
    {
        return $this->simulations;
    }

    /**
     * @param $simulations
     * @return $this
     */
    protected function setSimulations($simulations)
    {
        $this->simulations = $simulations;
        if ($this->session !== null) {
            $this->session->set($this->sessionKey, $simulations);
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function clear()
    {
        $this->simulations = array();
        if ($this->session !== null) {
            $this->session->set($this->sessionKey, array());
        }

        return $this;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->simulations());
    }

    /**
     * @return bool
     */
    public function isEmpty()
    {
        return $this->count() === 0;
    }

    /**
     * @param $node
     * @param $properties
     */
    public function set($node, $properties)
    {
        $simulations = $this->simulations();
        $simulations[$node] = $properties;
        $this->setSimulations($simulations);
    }

    /**
     * @param $name
     * @return bool
     */
    public function hasNode($name)
    {
        $simulations = $this->simulations();
        return array_key_exists($name, $simulations);
    }

    /**
     * @param $name
     * @return mixed
     * @throws ProgrammingError
     */
    public function getNode($name)
    {
        $simulations = $this->simulations();
        if (! array_key_exists($name, $simulations)) {
            throw new ProgrammingError('Trying to access invalid node %s', $name);
        }
        return $simulations[$name];
    }

    /**
     * @param $node
     * @return bool
     */
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
