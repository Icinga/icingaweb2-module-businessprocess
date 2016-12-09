<?php

namespace Icinga\Module\Businessprocess;

use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Exception\ProgrammingError;
use Icinga\User;

class Metadata
{
    protected $properties = array(
        'Title'         => null,
        'Description'   => null,
        'Owner'         => null,
        'AllowedUsers'  => null,
        'AllowedGroups' => null,
        'AllowedRoles'  => null,
        'Backend'       => null,
        'Statetype'     => null,
        // 'SLAHosts'      => null
    );

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

    public function permissionsAreSatisfied(Auth $auth = null)
    {
        if ($auth === null) {
            if (Icinga::app()->isCli()) {
                return true;
            } else {
                $auth = $this->getAuth();
            }
        }

        if (! $this->hasRestrictions()) {
            return true;
        }

        if (! $auth->isAuthenticated()) {
            return false;
        }

        return $this->userIsAllowed($auth->getUser());
    }

    public function userIsAllowed(User $user)
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
        foreach ($this->listAllowedGroups() as $groups) {
            foreach ($groups as $group) {
                if ($user->isMemberOf($group)) {
                    return true;
                }
            }
        }

        return false;
    }

    public function hasOneOfTheAllowedRoles(User $user)
    {
        foreach ($this->listAllowedRoles() as $roles) {
            foreach ($roles as $roleName) {
                foreach ($user->getRoles() as $role) {
                    if ($role->getName() === $roleName) {
                        return true;
                    }
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
