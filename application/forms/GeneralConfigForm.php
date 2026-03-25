<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Forms;

use ipl\Validator\GreaterThanValidator;
use ipl\Web\Compat\CompatForm;

class GeneralConfigForm extends CompatForm
{
    /** @var int Default number of maximum allowed processes in the sidebar menu */
    public const MAX_MENU_PROCESSES = 5;

    protected function assemble(): void
    {
        $this->addElement(
            'number',
            'max_menu_processes',
            [
                'label'       => $this->translate('Max Menu Processes'),
                'description' => $this->translate('Max allowed processes in sidebar menu'),
                'placeholder' => '5',
                'value'       => static::MAX_MENU_PROCESSES,
                'min'         => 0,
                'validators'  => [new GreaterThanValidator(['min' => 0])]
            ]
        );

        $this->addElement('submit', 'submit', ['label' => $this->translate('Save Changes')]);
    }
}
