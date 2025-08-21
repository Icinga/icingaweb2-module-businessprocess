<?php

/* Icinga Web 2 Business Process Module | (c) 2025 Icinga GmbH | GPLv2 */

namespace Icinga\Module\Businessprocess\Forms;

use ipl\Web\Compat\CompatForm;

class GeneralConfigForm extends CompatForm
{
    protected function assemble(): void
    {
        $this->addElement(
            'number',
            'no_menu_processes',
            [
                'label'       => $this->translate('Number of processes in sidebar menu'),
                'placeholder' => $this->translate('Defaults to 5 if empty'),
                'value'       => 5
            ]
        );

        $this->addElement(
            'submit',
            'submit',
            ['label' => $this->translate('Save Changes')]
        );
    }
}
