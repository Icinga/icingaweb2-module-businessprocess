<?php

namespace Icinga\Module\Businessprocess\Web\Form;

use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\BpConfig;

abstract class BpConfigBaseForm extends QuickForm
{
    /** @var LegacyStorage */
    protected $storage;

    /** @var BpConfig */
    protected $config;

    protected function listAvailableBackends()
    {
        $keys = [];
        $moduleManager = Icinga::app()->getModuleManager();
        if ($moduleManager->hasEnabled('monitoring')) {
            $keys = array_keys(Config::module('monitoring', 'backends')->toArray());
            $keys = array_combine($keys, $keys);
        }

        if ($moduleManager->hasEnabled('icingadb')) {
            $keys['_icingadb'] = 'Icinga DB';
        }

        return $keys;
    }

    public function setStorage(LegacyStorage $storage)
    {
        $this->storage = $storage;
        return $this;
    }

    public function setProcessConfig(BpConfig $config)
    {
        $this->config = $config;
        return $this;
    }

    protected function prepareMetadata(BpConfig $config)
    {
        $meta = $config->getMetadata();
        $auth = Auth::getInstance();
        $meta->set('Owner', $auth->getUser()->getUsername());

        if ($auth->hasPermission('businessprocess/showall')) {
            return true;
        }

        $prefixes = $auth->getRestrictions('businessprocess/prefix');
        if (! empty($prefixes) && ! $meta->nameIsPrefixedWithOneOf($prefixes)) {
            if (count($prefixes) === 1) {
                $this->getElement('name')->addError(sprintf(
                    $this->translate('Please prefix the name with "%s"'),
                    current($prefixes)
                ));
            } else {
                $this->getElement('name')->addError(sprintf(
                    $this->translate('Please prefix the name with one of "%s"'),
                    implode('", "', $prefixes)
                ));
            }

            return false;
        }

        return true;
    }
}
