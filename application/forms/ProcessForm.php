<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Form\BpConfigBaseForm;
use Icinga\Web\Notification;
use Icinga\Web\View;

class ProcessForm extends BpConfigBaseForm
{
    /** @var BpNode */
    protected $node;

    public function setup()
    {
        if ($this->node !== null) {
            /** @var View $view */
            $view = $this->getView();

            $this->addHtml(
                '<h2>' . $view->escape(
                    sprintf($this->translate('Modify "%s"'), $this->node->getAlias())
                ) . '</h2>'
            );
        }

        $this->addElement('text', 'name', [
            'label'         => $this->translate('ID'),
            'value'         => (string) $this->node,
            'required'      => true,
            'readonly'      => $this->node ? true : null,
            'description'   => $this->translate('This is the unique identifier of this process')
        ]);

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
            'multiOptions' => Node::getOperators()
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
     * @param BpNode $node
     * @return $this
     */
    public function setNode(BpNode $node)
    {
        $this->node = $node;
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
                    $this->translate('Process %s has been modified'),
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
