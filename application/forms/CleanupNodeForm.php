<?php

namespace Icinga\Module\Businessprocess\Forms;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Modification\ProcessChanges;
use Icinga\Module\Businessprocess\Web\Form\BpConfigBaseForm;
use Icinga\Module\Monitoring\Backend\MonitoringBackend;
use Icinga\Web\Session\SessionNamespace;
use ipl\Html\Html;
use ipl\Sql\Connection as IcingaDbConnection;

class CleanupNodeForm extends BpConfigBaseForm
{
    /** @var MonitoringBackend|IcingaDbConnection */
    protected $backend;

    /** @var BpConfig */
    protected $bp;

    /** @var SessionNamespace */
    protected $session;

    public function setup()
    {
        $this->addHtml(Html::tag('h2', $this->translate('Cleanup missing nodes')));

        $this->addElement('checkbox', 'cleanup_all', [
            'class'         => 'autosubmit',
            'label'         => $this->translate('Cleanup all missing nodes'),
            'description'   => $this->translate('Remove all missing nodes from config')
        ]);

        if ($this->getSentValue('cleanup_all') !== '1') {
            $this->addElement('multiselect', 'nodes', [
                    'label'        => $this->translate('Select nodes to cleanup'),
                    'required'     => true,
                    'size'         => 8,
                    'multiOptions' => $this->bp->getMissingChildren()
            ]);
        }
    }

    public function onSuccess()
    {
        $changes = ProcessChanges::construct($this->bp, $this->session);

        $nodesToCleanup = $this->getValue('cleanup_all') === '1'
            ? array_keys($this->bp->getMissingChildren())
            : $this->getValue('nodes');

        foreach ($nodesToCleanup as $nodeName) {
            $node = $this->bp->getNode($nodeName);
            $changes->deleteNode($node);
        }

        unset($changes);

        parent::onSuccess();
    }
}
