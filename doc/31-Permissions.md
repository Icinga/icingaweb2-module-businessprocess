<a id="Permission System"></a>Permission System
=================================================

The permission system of the module is based on permissions and restrictions.

Permissions
-----------

The module has five levels of permissions:

* Granting general module access allows a user to view business processes. (`module/businessprocess`)
* Create permissions allow to create new business processes. (`businessprocess/create`)
* Modify permissions allow to modify already existing ones. (`businessprocess/modify`)
* Permission to view all business processes regardless restrictions. (`businessprocess/showall`)
* Full permissions. (`businessprocess/*`)

Restrictions
-----------

There are two ways to configure restrictions: prefix-based and access controls

### Prefix-based

This option allows to limit access of a role to only business processes with a specific prefix. For this the ID (Configuration name) of a business process has to start with a prefix and it has to be set as restriction on the role. (`businessprocess/prefix`)

### Access controls

This option allows for more fine granular permissions based on user (`AllowedUsers`), group (`AllowedGroups`) and role (`AllowedRoles`). These attributes take a comma-separated list, get added to the header of the business process configuration file and limit access to the owner and the mentioned ones.
