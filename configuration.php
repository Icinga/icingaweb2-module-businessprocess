<?php

$section = $this->menuSection($this->translate('Availability'), array(
    'icon'     => 'img/icons/servicegroup.png',
    'priority' => 40
));
$section->add($this->translate('Business Processes'))->setUrl('bpapp/process/show');

