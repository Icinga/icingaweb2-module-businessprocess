<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;

class DeleteNodeForm extends QuickForm
{
    /** @var MonitoringBackend */
    protected $backend;

    /** @var BusinessProcess */
    protected $bp;

    /** @var BpNode */
    protected $node;

    /** @var array */
    protected $path;

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        $this->addElement('select', 'confirm', array(
            'label'        => $this->translate('Are you sure?'),
            'required'     => true,
            'description'  => $this->translate(
                'Do you really want to delete this node'
            ),
            'multiOptions' => $this->optionalEnum(
                array(
                'no'  => $this->translate('No'),
                'yes' => $this->translate('Yes'),
            ))
        ));
    }

    /**
     * @param MonitoringBackend $backend
     * @return $this
     */
    public function setBackend(MonitoringBackend $backend)
    {
        $this->backend = $backend;
        return $this;
    }

    /**
     * @param BusinessProcess $process
     * @return $this
     */
    public function setProcess(BusinessProcess $process)
    {
        $this->bp = $process;
        $this->setBackend($process->getBackend());
        return $this;
    }

    /**
     * @param BpNode $node
     * @return $this
     */
    public function setNode(BpNode $node)
    {
        $this->node = $node;
        return $this;
    }

    /**
     * @param array $path
     * @return $this
     */
    public function setPath(array $path)
    {
        $this->path = $path;
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
        $changes->deleteNode($this->node, $this->path);
        // Trigger session desctruction to make sure it get's stored.
        // TODO: figure out why this is necessary, might be an unclean shutdown on redirect
        unset($changes);

        parent::onSuccess();
    }
}
