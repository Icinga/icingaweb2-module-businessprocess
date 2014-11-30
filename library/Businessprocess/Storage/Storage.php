<?php

namespace Icinga\Module\Businessprocess\Storage;

use Icinga\Data\ConfigObject;
use Icinga\Module\Businessprocess\BusinessProcess;

abstract class Storage
{
    protected $config;

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
     * @return BusinessProcess
     */
    abstract public function loadProcess($name);

    /**
     */
    abstract public function storeProcess(BusinessProcess $name);
}
