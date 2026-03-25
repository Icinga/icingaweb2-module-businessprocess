<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Application\Config;
use Icinga\Module\Businessprocess\Forms\GeneralConfigForm;
use Icinga\Web\Notification;
use Icinga\Web\Widget\Tabs;
use ipl\Html\Contract\Form;
use ipl\Web\Compat\CompatController;

class ConfigController extends CompatController
{
    public function init(): void
    {
        $this->assertPermission('config/modules');

        parent::init();
    }

    public function generalAction(): void
    {
        $this->mergeTabs($this->Module()->getConfigTabs()->activate('general'));

        $config = Config::module('businessprocess');
        $form = (new GeneralConfigForm())
            ->populate($config->getSection('general'))
            ->on(Form::ON_SUBMIT, function (GeneralConfigForm $form) use ($config) {
                $config->setSection('general', $form->getValues());
                $config->saveIni();

                Notification::success($this->translate('New configuration saved successfully'));
            })->handleRequest($this->getServerRequest());

        $this->addContent($form);
    }

    /**
     * Merge tabs with other tabs contained in this tab panel
     *
     * @param Tabs $tabs
     *
     * @return void
     */
    protected function mergeTabs(Tabs $tabs): void
    {
        foreach ($tabs->getTabs() as $tab) {
            $this->tabs->add($tab->getName(), $tab);
        }
    }
}
