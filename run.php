<?php

use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;

$this->provideHook('monitoring/HostActions');
$this->provideHook('monitoring/ServiceActions');
$this->provideHook('monitoring/DetailviewExtension');
$this->provideHook('icingadb/HostActions');
$this->provideHook('icingadb/ServiceActions');
$this->provideHook('icingadb/icingadbSupport');
$this->provideHook('icingadb/ServiceDetailExtension');
//$this->provideHook('director/shipConfigFiles');

if (! static::exists('icingadb') || ! IcingadbSupport::useIcingaDbAsBackend()) {
    $this->addRoute('businessprocess/host/show', new Zend_Controller_Router_Route_Static(
        'businessprocess/host/show',
        [
            'controller'    => 'ido-host',
            'action'        => 'show',
            'module'        => 'businessprocess'
        ]
    ));

    $this->addRoute('businessprocess/service/show', new Zend_Controller_Router_Route_Static(
        'businessprocess/service/show',
        [
            'controller'    => 'ido-service',
            'action'        => 'show',
            'module'        => 'businessprocess'
        ]
    ));
}
