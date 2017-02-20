<a id="Installation"></a>Installation
=====================================

Requirements
------------

* Icinga Web 2 (&gt;= 2.4.1)
* PHP (&gt;= 5.3 or 7.x)

The Icinga Web 2 `monitoring` module needs to be configured and enabled.

Installation from .tar.gz
-------------------------

Download the latest version and extract it to a folder named `businessprocess`
in one of your Icinga Web 2 module path directories.

You might want to use a script as follows for this task:
```sh
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/Icinga/icingaweb2-module-businessprocess"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/businessprocess"
MODULE_VERSION="2.1.0"
URL="${REPO_URL}/archive/v${MODULE_VERSION}.tar.gz"
install -d -m 0755 "${TARGET_DIR}"
wget -q -O - "$URL" | tar xfz - -C "${TARGET_DIR}" --strip-components 1
```

Installation from GIT repository
--------------------------------

Another convenient method is the installation directly from our GIT repository.
Just clone the repository to one of your Icinga Web 2 module path directories.
It will be immediately ready for use:

```sh
ICINGAWEB_MODULEPATH="/usr/share/icingaweb2/modules"
REPO_URL="https://github.com/Icinga/icingaweb2-module-businessprocess"
TARGET_DIR="${ICINGAWEB_MODULEPATH}/businessprocess"
MODULE_VERSION="2.1.0"
git clone "${REPO_URL}" "${TARGET_DIR}"
```

You can now directly use our current GIT master or check out a specific version.

Enable the newly installed module
---------------------------------

Enable the `businessprocess` module either on the CLI by running

```sh
icingacli module enable businessprocess
```

Or go to your Icinga Web 2 frontend, choose `Configuration` -&gt; `Modules`...

![Choose Configuration - Modules](screenshot/01_installation/101_menu-configuration-modules.png)

...choose the `businessprocess` module and `enable` it:

![Enable the module](screenshot/01_installation/102_enable-module.png)

It might afterwards be necessary to refresh your web browser to be sure that
newly provided styling is loaded.

Create your first Business Process definition
---------------------------------------------

That's it, *Business Process* is now ready for use. Please read more on [how to get started](02-Getting-Started.md).
