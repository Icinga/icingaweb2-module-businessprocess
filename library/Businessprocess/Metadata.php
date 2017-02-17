<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Exception\ProgrammingError;
use Icinga\User;

class Metadata
{
    /** @var string Configuration name */
    protected $name;

    protected $properties = array(
        'Title'         => null,
        'Description'   => null,
        'Owner'         => null,
        'AllowedUsers'  => null,
        'AllowedGroups' => null,
        'AllowedRoles'  => null,
        'AddToMenu'     => null,
        'Backend'       => null,
        'Statetype'     => null,
        // 'SLAHosts'      => null
    );

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function getTitle()
    {
        if ($this->has('Title')) {
            return $this->get('Title');
        } else {
            return $this->name;
        }
    }

    public function getExtendedTitle()
    {
        $title = $this->getTitle();

        if ($title === $this->name) {
            return $title;
        } else {
            return sprintf('%s (%s)', $title, $this->name);
        }
    }

    public function getProperties()
    {
        return $this->properties;
    }

    public function hasKey($key)
    {
        return array_key_exists($key, $this->properties);
    }

    public function get($key, $default = null)
    {
        $this->assertKeyExists($key);
        if ($this->properties[$key] === null) {
            return $default;
        }

        return $this->properties[$key];
    }

    public function set($key, $value)
    {
        $this->assertKeyExists($key);
        $this->properties[$key] = $value;

        return $this;
    }

    public function isNull($key)
    {
        return null === $this->get($key);
    }

    public function has($key)
    {
        return null !== $this->get($key);
    }

    protected function assertKeyExists($key)
    {
        if (! $this->hasKey($key)) {
            throw new ProgrammingError('Trying to access invalid header key: %s', $key);
        }

        return $this;
    }

    public function hasRestrictions()
    {
        return ! (
            $this->isNull('AllowedUsers')
            && $this->isNull('AllowedGroups')
            && $this->isNull('AllowedRoles')
        );
    }

    protected function getAuth()
    {
        return Auth::getInstance();
    }

    public function canModify(Auth $auth = null)
    {
        if ($auth === null) {
            if (Icinga::app()->isCli()) {
                return true;
            } else {
                $auth = $this->getAuth();
            }
        }

        return $this->canRead($auth) && (
            $auth->hasPermission('businessprocess/modify')
            || $this->ownerIs($auth->getUser()->getUsername())
        );
    }

    public function canRead(Auth $auth = null)
    {
        if ($auth === null) {
            if (Icinga::app()->isCli()) {
                return true;
            } else {
                $auth = $this->getAuth();
            }
        }

        $prefixes = $auth->getRestrictions('businessprocess/prefix');
        if (! empty($prefixes)) {
            if (! $this->nameIsPrefixedWithOneOf($prefixes)) {
                return false;
            }
        }

        if ($auth->hasPermission('businessprocess/showall')) {
            return true;
        }

        if (! $this->hasRestrictions()) {
            return true;
        }

        if (! $auth->isAuthenticated()) {
            return false;
        }

        return $this->userCanRead($auth->getUser());
    }

    public function nameIsPrefixedWithOneOf(array $prefixes)
    {
        foreach ($prefixes as $prefix) {
            if (substr($this->name, 0, strlen($prefix)) === $prefix) {
                return true;
            }
        }

        return false;
    }

    protected function userCanRead(User $user)
    {
        $username = $user->getUsername();

        return $this->ownerIs($username)
            || $this->isInAllowedUserList($username)
            || $this->isMemberOfAllowedGroups($user)
            || $this->hasOneOfTheAllowedRoles($user);
    }

    public function ownerIs($username)
    {
        return $this->get('Owner') === $username;
    }

    public function listAllowedUsers()
    {
        // TODO: $this->get('AllowedUsers', array());
        $list = $this->get('AllowedUsers');
        if ($list === null) {
            return array();
        } else {
            return $this->splitCommaSeparated($list);
        }
    }

    public function listAllowedGroups()
    {
        $list = $this->get('AllowedGroups');
        if ($list === null) {
            return array();
        } else {
            return $this->splitCommaSeparated($list);
        }
    }

    public function listAllowedRoles()
    {
        $list = $this->get('AllowedRoles');
        if ($list === null) {
            return array();
        } else {
            return $this->splitCommaSeparated($list);
        }
    }

    public function isInAllowedUserList($username)
    {
        foreach ($this->listAllowedUsers() as $allowedUser) {
            if ($username === $allowedUser) {
                return true;
            }
        }

        return false;
    }

    public function isMemberOfAllowedGroups(User $user)
    {
        foreach ($this->listAllowedGroups() as $group) {
            if ($user->isMemberOf($group)) {
                return true;
            }
        }

        return false;
    }

    public function hasOneOfTheAllowedRoles(User $user)
    {
        foreach ($this->listAllowedRoles() as $roleName) {
            foreach ($user->getRoles() as $role) {
                if ($role->getName() === $roleName) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function splitCommaSeparated($string)
    {
        return preg_split('/\s*,\s*/', $string, -1, PREG_SPLIT_NO_EMPTY);
    }
}
