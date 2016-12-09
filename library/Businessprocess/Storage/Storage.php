<?php

namespace Icinga\Module\Businessprocess\Storage;

use Icinga\Data\ConfigObject;
use Icinga\Module\Businessprocess\BusinessProcess;
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
     * @return array
     */
    abstract public function listProcesses();

    /**
     * @param $name
     * @return BusinessProcess
     */
    abstract public function loadProcess($name);

    /**
     * @param BusinessProcess $name
     * @return mixed
     */
    abstract public function storeProcess(BusinessProcess $name);

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
