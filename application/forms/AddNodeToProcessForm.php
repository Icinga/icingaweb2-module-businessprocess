<?php

namespace Icinga\Module\Businessprocess\Forms;

use Exception;
use Icinga\Application\Icinga;
use Icinga\Exception\ConfigurationError;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Exception\ModificationError;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Module\Businessprocess\Storage\Storage;
use Icinga\Module\Businessprocess\Web\Url;
use Icinga\Web\Session;
use ipl\Html\Form;
use ipl\Html\FormDecorator\DdDtDecorator;
use ipl\I18n\Translation;
use ipl\Web\Common\CsrfCounterMeasure;

class AddNodeToProcessForm extends Form
{
    use Translation;
    use CsrfCounterMeasure;

    /** @var ?Storage */
    protected $storage;

    /** @var ?Session\SessionNamespace */
    protected $session;

    /** @var ?string */
    protected $nodeName;

    /** @var BpConfig */
    protected $bpConfig;

    protected $changes;

    /**
     * Set the storage
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
     * Set the session
     *
     * @param Session\SessionNamespace $session
     *
     * @return $this
     */
    public function setSession(Session\SessionNamespace $session): self
    {
        $this->session = $session;

        return $this;
    }

    public function setBpConfig(BpConfig $config)
    {
        $this->bpConfig = $config;

        return $this;
    }

    public function setNodeName(string $nodeName): self
    {
        $this->nodeName = $nodeName;

        return $this;
    }

    public function getNodeName(): ?string
    {
        return $this->nodeName;
    }

    protected function assemble()
    {
        $this->createCsrfCounterMeasure(Session::getSession()->getId());
        $this->setDefaultElementDecorator(new DdDtDecorator());

        $this->addElement('select', 'config', [
            'label'             => $this->translate('File Name'),
            'required'          => true,
            'class'             => 'autosubmit',
            'options'           => array_merge(
                ['' => $this->translate('Please choose')],
                $this->storage->listProcesses()
            ),
            'disabledOptions'   => [''],
            'description'       => $this->translate('Choose a configuration file')
        ]);

        $newParentNode = null;
        $configName = $this->getValue('config');
        if ($configName !== null) {
            try {
                $this->setBpConfig($this->storage->loadProcess($configName));
                $changes = ProcessChanges::construct($this->bpConfig, $this->session);
                $parent = $this->bpConfig->createBp('Unbound');
                $changes->addChildrenToNode($this->getNodeName(), $parent);
                $newParentNode = $this->getPopulatedValue('parent');
                $newParentNode = $newParentNode ? $this->bpConfig->getNode($newParentNode) : null;
                if ($newParentNode && ! $this->bpConfig->hasNode($this->getNodeName())) {
                    $changes->addChildrenToNode($this->getNodeName(), $newParentNode);
                }

                if ($this->getPopulatedValue('from') !== null) {
                    if (! $this->bpConfig->getMetadata()->isManuallyOrdered()) {
                        $changes->applyManualOrder();
                    }


                    try {
                        $changes->moveNode(
                            $this->bpConfig->getNode($this->getNodeName()),
                            $this->getPopulatedValue('from'),
                            $this->getPopulatedValue('to'),
                            $this->getPopulatedValue('parent')
                        );
                    } catch (Exception $e) {
                        throw new Exception($e->getMessage());
                        /*$this->notifyError($e->getMessage());
                    Icinga::app()->getResponse()
                        // Web 2's JS forces a content update for non-200s. Our own JS
                        // can't prevent this, hence we're not making this a 400 :(
                        //->setHttpResponseCode(400)
                        ->setHeader('X-Icinga-Container', 'ignore')
                        ->sendResponse();
                    exit;*/
                    }
                }
                // Trigger session destruction to make sure it get's stored.
                unset($changes);
            } catch (Exception $e) {
                throw new ConfigurationError(
                    'Config file %s.conf is invalid, please choose another one: %s',
                    $configName,
                    $e->getMessage()
                );
            }

            $tree = (new TreeRenderer($this->bpConfig))
                ->setUrl(Url::fromRequest())
                ->setExtraChild(! $newParentNode ? $this->getNodeName() : null)
                ->unlock();

            $tree->setSort($tree->getDefaultSort());

            $this->add($tree);

            $this->addElement('submit', 'submit');
        }
    }

    protected function onSuccess()
    {

    }
}
