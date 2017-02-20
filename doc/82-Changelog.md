<a id="Changelog"></a>Changelog
===============================

2.1.0
-----

### Fixed issues and related features
* You can find issues and feature requests related to this release on our
  [roadmap](https://github.com/Icinga/icingaweb2-module-businessprocess/milestone/4?closed=1)

### Usability and visualization
* Missing nodes are now shown in a generic error notice
* `Unbound nodes` (nodes not being shown at top level and not in use as a sub
  node) are now reachable through a fake node
* A bug with the Chrome browser showing messed up checkboxes has been fixed

### State calculation
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
