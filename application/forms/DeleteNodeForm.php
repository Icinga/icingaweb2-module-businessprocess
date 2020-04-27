<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;

class DeleteNodeForm extends QuickForm
{
    /** @var MonitoringBackend */
    protected $backend;

    /** @var BpConfig */
    protected $bp;

    /** @var Node */
    protected $node;

    /** @var BpNode */
    protected $parentNode;

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        $node = $this->node;
        $view = $this->getView();
        $this->addHtml(
            '<h2>' . $view->escape(
                sprintf($this->translate('Delete "%s"'), $node->getAlias())
            ) . '</h2>'
        );

        $biLink = $view->qlink(
            $node->getAlias(),
            'businessprocess/node/impact',
            array('name' => $node->getName()),
            array('data-base-target' => '_next')
        );
        $this->addHtml(
            '<p>' . sprintf(
                $view->escape(
                    $this->translate('Unsure? Show business impact of "%s"')
                ),
                $biLink
            ) . '</p>'
        );

        if ($this->parentNode) {
            $yesMsg = sprintf(
                $this->translate('Delete from %s'),
                $this->parentNode->getAlias()
            );
        } else {
            $yesMsg = sprintf(
                $this->translate('Delete root node "%s"'),
                $this->node->getAlias()
            );
        }

        $this->addElement('select', 'confirm', array(
            'label'        => $this->translate('Are you sure?'),
            'required'     => true,
            'description'  => $this->translate(
                'Do you really want to delete this node?'
            ),
            'multiOptions' => $this->optionalEnum(array(
                'no'  => $this->translate('No'),
                'yes' => $yesMsg,
                'all' => sprintf($this->translate('Delete all occurrences of %s'), $node->getAlias()),
            ))
        ));
    }

    /**
     * @param MonitoringBackend $backend
     * @return $this
     */
    public function setBackend($backend)
    {
        $this->backend = $backend;
        return $this;
    }

    /**
     * @param BpConfig $process
     * @return $this
     */
    public function setProcess(BpConfig $process)
    {
        $this->bp = $process;
        $this->setBackend($process->getBackend());
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
        $changes = ProcessChanges::construct($this->bp, $this->session);

        $confirm = $this->getValue('confirm');
        switch ($confirm) {
            case 'yes':
                $changes->deleteNode($this->node, $this->parentNode === null ? null : $this->parentNode->getName());
                break;
            case 'all':
                $changes->deleteNode($this->node);
                break;
            case 'no':
                $this->setSuccessMessage($this->translate('Well, maybe next time'));
        }

        switch ($confirm) {
            case 'yes':
            case 'all':
                if ($this->successUrl === null) {
                    $this->successUrl = clone $this->getRequest()->getUrl();
                }

                $this->successUrl->getParams()->remove(array('action', 'deletenode'));
        }

        // Trigger session desctruction to make sure it get's stored.
        // TODO: figure out why this is necessary, might be an unclean shutdown on redirect
        unset($changes);

        parent::onSuccess();
    }
}
