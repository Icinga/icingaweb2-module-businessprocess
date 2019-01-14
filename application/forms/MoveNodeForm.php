<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Application\Icinga;
use Icinga\Exception\Http\HttpException;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Form\CsrfToken;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Web\Session\SessionNamespace;

class MoveNodeForm extends QuickForm
{
    /** @var BpConfig */
    protected $bp;

    /** @var Node */
    protected $node;

    /** @var BpNode */
    protected $parentNode;

    /** @var SessionNamespace */
    protected $session;

    public function __construct($options = null)
    {
        parent::__construct($options);

        // Zend's plugin loader reverses the order of added prefix paths thus trying our paths first before trying
        // Zend paths
        $this->addPrefixPaths(array(
            array(
                'prefix'    => 'Icinga\\Web\\Form\\Element\\',
                'path'      => Icinga::app()->getLibraryDir('Icinga/Web/Form/Element'),
                'type'      => static::ELEMENT
            ),
            array(
                'prefix'    => 'Icinga\\Web\\Form\\Decorator\\',
                'path'      => Icinga::app()->getLibraryDir('Icinga/Web/Form/Decorator'),
                'type'      => static::DECORATOR
            )
        ));
    }

    public function setup()
    {
        $this->addElement(
            'text',
            'parent',
            [
                'allowEmpty'    => true,
                'filters'       => ['Null'],
                'validators'    => [
                    ['Callback', true, [
                        'callback'  => function($name) {
                            return empty($name) || $this->bp->hasBpNode($name);
                        },
                        'messages'  => [
                            'callbackValue' => $this->translate('No process found with name %value%')
                        ]
                    ]]
                ]
            ]
        );
        $this->addElement(
            'number',
            'from',
            [
                'required'  => true,
                'min'       => 0
            ]
        );
        $this->addElement(
            'number',
            'to',
            [
                'required'  => true,
                'min'       => 0
            ]
        );
        $this->addElement(
            'hidden',
            'csrfToken',
            [
                'required'  => true
            ]
        );

        $this->setSubmitLabel('movenode');
    }

    /**
     * @param BpConfig $process
     * @return $this
     */
    public function setProcess(BpConfig $process)
    {
        $this->bp = $process;
        return $this;
    }

    /**
     * @param Node $node
     * @return $this
     */
    public function setNode(Node $node)
    {
        $this->node = $node;
        return $this;
    }

    /**
     * @param BpNode|null $node
     * @return $this
     */
    public function setParentNode(BpNode $node = null)
    {
        $this->parentNode = $node;
        return $this;
    }

    /**
     * @param SessionNamespace $session
     * @return $this
     */
    public function setSession(SessionNamespace $session)
    {
        $this->session = $session;
        return $this;
    }

    public function onSuccess()
    {
        if (! CsrfToken::isValid($this->getValue('csrfToken'))) {
            throw new HttpException(403, 'nope');
        }

        $changes = ProcessChanges::construct($this->bp, $this->session);
        if (! $this->bp->getMetadata()->isManuallyOrdered()) {
            $changes->applyManualOrder();
        }

        $changes->moveNode(
            $this->node,
            $this->getValue('from'),
            $this->getValue('to'),
            $this->getValue('parent'),
            $this->parentNode !== null ? $this->parentNode->getName() : null
        );

        // Trigger session destruction to make sure it get's stored.
        unset($changes);

        $this->setSuccessMessage($this->translate('Node order updated'));
        parent::onSuccess();
    }

    public function hasBeenSent()
    {
        return true; // This form has no id
    }
}
