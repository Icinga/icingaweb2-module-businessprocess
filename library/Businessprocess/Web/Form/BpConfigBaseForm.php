<?php

namespace Icinga\Module\Businessprocess\Web\Form;

use Icinga\Application\Config;
use Icinga\Application\Icinga;
use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Storage\Storage;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;
use ipl\Sql\Connection as IcingaDbConnection;

abstract class BpConfigBaseForm extends QuickForm
{
    /** @var Storage */
    protected $storage;

    /** @var BpConfig */
    protected $bp;

    /** @var MonitoringBackend|IcingaDbConnection*/
    protected $backend;

    /** @var SessionNamespace */
    protected $session;

    protected function listAvailableBackends()
    {
        $keys = [];
        $moduleManager = Icinga::app()->getModuleManager();
        if ($moduleManager->hasEnabled('monitoring')) {
            $keys = array_keys(Config::module('monitoring', 'backends')->toArray());
            $keys = array_combine($keys, $keys);
        }

        return $keys;
    }

    /**
     * Set the storage to use
     *
     * @param Storage $storage
     *
     * @return $this
     */
    public function setStorage(Storage $storage): self
    {
        $this->storage = $storage;

        return $this;
    }

    /**
     * Set the config to use
     *
     * @param BpConfig $config
     *
     * @return $this
     */
    public function setProcess(BpConfig $config): self
    {
        $this->bp = $config;
        $this->setBackend($config->getBackend());

        return $this;
    }

    /**
     * Set the backend to use
     *
     * @param MonitoringBackend|IcingaDbConnection $backend
     *
     * @return $this
     */
    public function setBackend($backend): self
    {
        $this->backend = $backend;

        return $this;
    }

    /**
     * Set the session namespace to use
     *
     * @param SessionNamespace $session
     *
     * @return $this
     */
    public function setSession(SessionNamespace $session): self
    {
        $this->session = $session;

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

    protected function setPreferredDecorators()
    {
        parent::setPreferredDecorators();

        $this->setAttrib('class', $this->getAttrib('class') . ' bp-form');

        return $this;
    }
}
