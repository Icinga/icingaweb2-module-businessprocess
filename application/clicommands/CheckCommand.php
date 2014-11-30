<?php

namespace Icinga\Module\Bpapp\Clicommands;

use Icinga\Cli\Command;
use Icinga\Module\Bpapp\BusinessProcess;
use Icinga\Application\Config;
use Icinga\Module\Monitoring\Backend;

class CheckCommand extends Command
{
    protected $config;
    protected $bpconf;
    protected $bpname;
    protected $views;
    protected $filename;
    protected $backend;

    public function init()
    {
        $this->app->getModuleManager()->loadModule('monitoring');
        $this->config = $this->Config();
        $this->readConfig();
        $this->prepareBackend();
    }

    /**
     * Check a specific process
     *
     * Blabla
     */
    public function processAction()
   {
        $bp = BusinessProcess::parse($this->filename);
        $node = $bp->getNode($this->params->shift());
        if ($this->params->get('soft-states')) {
          $bp->useSoftStates();
        }
        $bp->retrieveStatesFromBackend($this->backend);
        printf("Business Process %s: %s\n", $node->getStateName(), $node->getAlias());
        exit($node->getState());

    }

    // TODO: Remove this
    protected function prepareBackend()
    {
        if ($this->backend === null) {
            $name = $this->config->{'global'}->get('default_backend');
            if (isset($this->bpconf->backend)) {
                $name = $this->bpconf->backend;
            }
            $this->backend = Backend::createBackend($name);
        }
        return $this->backend;
    }

    // TODO: Remove this
    protected function readConfig()
    {
        $this->views = array();
        $this->aliases = array();
        foreach ($this->config as $key => $val) {
            if (! preg_match('~^view-(.+)$~', $key, $match)) continue;
            $this->views[$match[1]] = (object) $val->toArray();
            $this->aliases[(string) $val->title] = $match[1];
            if ($val->aliases) {
                foreach (preg_split('~\s*,\s*~', $val->aliases, -1, PREG_SPLIT_NO_EMPTY) as $alias) {
                    $this->aliases[$alias] = $match[1];
                }
            }
        }
        $bpname = $this->params->get('bp', key($this->views));

        if (array_key_exists($bpname, $this->aliases)) {
            $bpname = $this->aliases[$bpname];
        }
        if (! array_key_exists($bpname, $this->views)) {
            throw new Exception('Got invalid bp name: ' . $bpname);
        }
        $this->bpconf = $this->views[$bpname];
        $this->bpname = $bpname;

        $this->filename = $this->config->global->bp_config_dir
            . '/' . $this->bpconf->file . '.conf';
    }
}
