<a id="Changelog"></a>Changelog
===============================

2.2.0
-----

### Issues and Features
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-businessprocess/milestone/6?closed=1)

### New Dependency

* The module now depends on the [Icinga PHP Library (ipl)](https://github.com/Icinga/icingaweb2-module-ipl)

### New Features

* Nodes can now be reordered by using Drag'n'Drop
  [#123](https://github.com/Icinga/icingaweb2-module-businessprocess/issues/123)
* Importing nodes from other process configurations is now fully supported
  [#133](https://github.com/Icinga/icingaweb2-module-businessprocess/issues/133)

### Usability and Visualization

* Lighter Design for the tree view and breadcrumbs
* Breadcrumbs provide a way back to the global overview now
* Info urls to external sites now open in a new browser tab
* Linked processes are now shown as part of a node's impact

### Permissions

* Users restricted by the monitoring module's `monitoring/filter/objects`
  restriction now get a properly filtered list of hosts and services
  while adding new nodes.
  [#67](https://github.com/Icinga/icingaweb2-module-businessprocess/issues/67)
* Users with the permission `businessprocess/showall` were previously not able
  to see configurations if they were also restricted by other roles.
  [#200](https://github.com/Icinga/icingaweb2-module-businessprocess/issues/200)

2.1.0
-----

### Issues and Features
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-businessprocess/milestone/4?closed=1)

### Usability and Visualization
* Missing nodes are now shown in a generic error notice
* `Unbound nodes` (nodes not being shown at top level and not in use as a sub
  node) are now reachable through a fake node
* A bug with the Chrome browser showing messed up checkboxes has been fixed

### State Calculation
* Missing nodes are now considered being `UNKNOWN` (or `UNREACHABLE` when
  being a host node). The former behaviour comes from Icinga 1.x, as a reload
  there had the potential to trigger false alarms. This is no longer an issue
  with Icinga 2.x, allowing us to be strict once again when it goes to missing
  nodes
* Linking nodes from other process configuration files (still an undocumented
  feature) has been broken shortly before 2.0.0, this has now been fixed

### Permissions
* Permissions have not been enforced as they should have been in 2.0.0, some
  links and operations have been accessible to roles that haven't been granted
  such. This has now been fixed
* While we allow for granular permissions that can be persisted in every process
  configuration file (still an undocumented feature), there is now also a pretty
  simple but effective way of restricting access to your business processes based
  on process name prefixes.

2.0.0
-----

* First officially stable version
