<?php

namespace Icinga\Module\Businessprocess\Storage;

use Icinga\Data\ConfigObject;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Metadata;

abstract class Storage
{
    /**
     * @var ConfigObject
     */
    protected $config;

    /**
     * Storage constructor.
     * @param ConfigObject $config
     */
    public function __construct(ConfigObject $config)
    {
        $this->config = $config;
        $this->init();
    }

    protected function init()
    {
    }

    /**
     * All processes readable by the current user
     *
     * The returned array has the form <process name> => <nice title>, sorted
     * by title
     *
     * @return array
     */
    abstract public function listProcesses();

    /**
     * All process names readable by the current user
     *
     * The returned array has the form <process name> => <process name> and is
     * sorted
     *
     * @return array
     */
    abstract public function listProcessNames();

    /**
     * All available process names, regardless of eventual restrictions
     *
     * @return array
     */
    abstract public function listAllProcessNames();

    /**
     * Whether a configuration with the given name exists
     *
     * @param $name
     *
     * @return bool
     */
    abstract public function hasProcess($name);

    /**
     * @param $name
     * @return BpConfig
     */
    abstract public function loadProcess($name);

    /**
     * Store eventual changes applied to the given configuration
     *
     * @param BpConfig $config
     *
     * @return mixed
     */
    abstract public function storeProcess(BpConfig $config);

    /**
     * @param $name
     * @return bool Whether the process has been deleted
     */
    abstract public function deleteProcess($name);

    /**
     * @param  string $name
     * @return Metadata
     */
    abstract public function loadMetadata($name);
}
