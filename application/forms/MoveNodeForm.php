<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Application\Icinga;
use Icinga\Application\Web;
use Icinga\Exception\Http\HttpException;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Exception\ModificationError;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Form\BpConfigBaseForm;
use Icinga\Module\Businessprocess\Web\Form\CsrfToken;
use Icinga\Web\Session;
use Icinga\Web\Session\SessionNamespace;

class MoveNodeForm extends BpConfigBaseForm
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
                        'callback'  => function ($name) {
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

    public function onSuccess()
    {
        if (! CsrfToken::isValid($this->getValue('csrfToken'))) {
            throw new HttpException(403, 'nope');
        }

        $changes = ProcessChanges::construct($this->bp, $this->session);
        if (! $this->bp->getMetadata()->isManuallyOrdered()) {
            $changes->applyManualOrder();
        }

        try {
            $changes->moveNode(
                $this->node,
                $this->getValue('from'),
                $this->getValue('to'),
                $this->getValue('parent'),
                $this->parentNode !== null ? $this->parentNode->getName() : null
            );
        } catch (ModificationError $e) {
            $this->notifyError($e->getMessage());
            /** @var Web $app */
            $app = Icinga::app();
            $app->getResponse()
                // Web 2's JS forces a content update for non-200s. Our own JS
                // can't prevent this, hence we're not making this a 400 :(
                //->setHttpResponseCode(400)
                ->setHeader('X-Icinga-Container', 'ignore')
                ->sendResponse();
            exit;
        }

        // Trigger session destruction to make sure it get's stored.
        unset($changes);

        $this->notifySuccess($this->translate('Node order updated'));

        $response = $this->getRequest()->getResponse()
            ->setHeader('X-Icinga-Container', 'ignore')
            ->setHeader('X-Icinga-Extra-Updates', implode(';', [
                $this->getRequest()->getHeader('X-Icinga-Container'),
                $this->getSuccessUrl()->getAbsoluteUrl()
            ]));

        Session::getSession()->write();
        $response->sendResponse();
        exit;
    }

    public function hasBeenSent()
    {
        return true; // This form has no id
    }
}
