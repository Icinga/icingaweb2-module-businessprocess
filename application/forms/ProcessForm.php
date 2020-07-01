<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Common\IcingadbDatabase;
use Icinga\Module\Businessprocess\IcingaDbBackend;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Web\Form\QuickForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Notification;
use Icinga\Web\Session\SessionNamespace;

class ProcessForm extends QuickForm
{
    /** @var MonitoringBackend|IcingadbDatabase */
    protected $backend;

    /** @var BpConfig */
    protected $bp;

    /** @var BpNode */
    protected $node;

    protected $objectList = array();

    protected $processList = array();

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        if ($this->node === null) {
            $this->addElement('text', 'name', array(
                'label'        => $this->translate('ID'),
                'required'     => true,
                'description' => $this->translate(
                    'This is the unique identifier of this process'
                ),
            ));
        } else {
            $this->addHtml(
                '<h2>' . $this->getView()->escape(
                    sprintf($this->translate('Modify "%s"'), $this->node->getAlias())
                ) . '</h2>'
            );
        }

        $this->addElement('text', 'alias', array(
            'label'        => $this->translate('Display Name'),
            'description' => $this->translate(
                'Usually this name will be shown for this node. Equals ID'
                . ' if not given'
            ),
        ));

        $this->addElement('select', 'operator', array(
            'label'        => $this->translate('Operator'),
            'required'     => true,
            'multiOptions' => array(
                '&' => $this->translate('AND'),
                '|' => $this->translate('OR'),
                '!' => $this->translate('NOT'),
                '1' => $this->translate('MIN 1'),
                '2' => $this->translate('MIN 2'),
                '3' => $this->translate('MIN 3'),
                '4' => $this->translate('MIN 4'),
                '5' => $this->translate('MIN 5'),
                '6' => $this->translate('MIN 6'),
                '7' => $this->translate('MIN 7'),
                '8' => $this->translate('MIN 8'),
                '9' => $this->translate('MIN 9'),
            )
        ));

        if ($this->node !== null) {
            $display = $this->node->getDisplay() ?: 1;
        } else {
            $display = 1;
        }
        $this->addElement('select', 'display', array(
            'label'        => $this->translate('Visualization'),
            'required'     => true,
            'description'  => $this->translate(
                'Where to show this process'
            ),
            'multiOptions' => array(
                "$display" => $this->translate('Toplevel Process'),
                '0' => $this->translate('Subprocess only'),
            )
        ));

        $this->addElement('text', 'url', array(
            'label'        => $this->translate('Info URL'),
            'description' => $this->translate(
                'URL pointing to more information about this node'
            )
        ));

        if ($node = $this->node) {
            if ($node->hasAlias()) {
                $this->getElement('alias')->setValue($node->getAlias());
            }
            $this->getElement('operator')->setValue($node->getOperator());
            $this->getElement('display')->setValue($node->getDisplay());
            if ($node->hasInfoUrl()) {
                $this->getElement('url')->setValue($node->getInfoUrl());
            }
        }
    }

    /**
     * @param MonitoringBackend|IcingadbDatabase $backend
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
     * @param BpNode $node
     * @return $this
     */
    public function setNode(BpNode $node)
    {
        $this->node = $node;
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

        $modifications = array();
        $alias    = $this->getValue('alias');
        $operator = $this->getValue('operator');
        $display  = $this->getValue('display');
        $url      = $this->getValue('url');
        if (empty($url)) {
            $url = null;
        }
        if (empty($alias)) {
            $alias = null;
        }
        // TODO: rename

        if ($node = $this->node) {
            if ($display !== $node->getDisplay()) {
                $modifications['display'] = $display;
            }
            if ($operator !== $node->getOperator()) {
                $modifications['operator'] = $operator;
            }
            if ($url !== $node->getInfoUrl()) {
                $modifications['infoUrl'] = $url;
            }
            if ($alias !== $node->getAlias()) {
                $modifications['alias'] = $alias;
            }
        } else {
            $modifications = array(
                'display'    => $display,
                'operator'   => $operator,
                'infoUrl'    => $url,
                'alias'      => $alias,
            );
        }

        if (! empty($modifications)) {
            if ($this->node === null) {
                $changes->createNode($this->getValue('name'), $modifications);
            } else {
                $changes->modifyNode($this->node, $modifications);
            }

            Notification::success(
                sprintf(
                    'Process %s has been modified',
                    $this->bp->getName()
                )
            );
        }

        // Trigger session destruction to make sure it get's stored.
        // TODO: figure out why this is necessary, might be an unclean shutdown on redirect
        unset($changes);

        parent::onSuccess();
    }
}
