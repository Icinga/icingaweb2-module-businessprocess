<?php

$section = $this->menuSection(N_('Overview'))
    ->add($this->translate('Business Processes'))
    ->setPriority(45)
    ->setUrl('businessprocess');

$this->providePermission('businessprocess/create', 'Allow to create new configs');
$this->providePermission('businessprocess/modify', 'Allow to modify processes');
