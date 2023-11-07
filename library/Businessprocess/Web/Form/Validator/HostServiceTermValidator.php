<?php

namespace Icinga\Module\Businessprocess\Web\Form\Validator;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\ServiceNode;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use ipl\I18n\Translation;
use ipl\Validator\BaseValidator;
use ipl\Web\FormElement\TermInput\Term;
use LogicException;

class HostServiceTermValidator extends BaseValidator
{
    use Translation;

    /** @var ?BpNode */
    protected $parent;

    /**
     * Set the affected process
     *
     * @param BpNode $parent
     *
     * @return $this
     */
    public function setParent(BpNode $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    public function isValid($terms)
    {
        if ($this->parent === null) {
            throw new LogicException('Missing parent process. Cannot validate terms.');
        }

        if (! is_array($terms)) {
            $terms = [$terms];
        }

        $isValid = true;
        $testConfig = new BpConfig();

        foreach ($terms as $term) {
            /** @var Term $term */
            [$hostName, $serviceName] = BpConfig::splitNodeName($term->getSearchValue());
            if ($serviceName !== null && $serviceName !== 'Hoststatus') {
                $node = $testConfig->createService($hostName, $serviceName);
            } else {
                $node = $testConfig->createHost($hostName);
                if ($serviceName === null) {
                    $term->setSearchValue(BpConfig::joinNodeName($hostName, 'Hoststatus'));
                }
            }

            if ($this->parent->hasChild($term->getSearchValue())) {
                $term->setMessage($this->translate('Already defined in this process'));
                $isValid = false;
            } else {
                $testConfig->getNode('__unbound__')
                    ->addChild($node);
            }
        }

        if ($this->parent->getBpConfig()->getBackend() instanceof MonitoringBackend) {
            MonitoringState::apply($testConfig);
        } else {
            IcingaDbState::apply($testConfig);
        }

        foreach ($terms as $term) {
            /** @var Term $term */
            $node = $testConfig->getNode($term->getSearchValue());
            if ($node->isMissing()) {
                if ($node instanceof ServiceNode) {
                    $term->setMessage($this->translate('Service not found'));
                } else {
                    $term->setMessage($this->translate('Host not found'));
                }

                $isValid = false;
            } else {
                $term->setLabel($node->getAlias());
                $term->setClass($node->getObjectClassName());
            }
        }

        return $isValid;
    }
}
