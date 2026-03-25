<?php

// SPDX-FileCopyrightText: 2021 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\Web\Component;

use Exception;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Storage\Storage;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class DashboardFullscreen extends BaseHtmlElement
{
    protected $contentSeparator = "\n";

    protected $tag = 'div';

    protected $defaultAttributes = ['class' => 'overview-dashboard', 'data-base-target' => '_next'];

    /** @var Storage */
    protected $storage;

    /**
     * Dashboard constructor.
     * @param Storage $storage
     */
    protected function __construct(Storage $storage)
    {
        $this->storage = $storage;
    }

    protected function assemble()
    {
        $processes = $this->storage->listProcessNames();
        if (empty($processes)) {
            $this->add(Html::tag(
                'div',
                [
                    Html::tag('h1', mt('businessprocess', 'Not available')),
                    Html::tag('p', mt('businessprocess', 'No Business Process has been defined for you'))
                ]
            ));
        }

        foreach ($processes as $name) {
            $meta = $this->storage->loadMetadata($name);
            $title = $meta->get('Title');

            if ($title === null) {
                $title = $name;
            }

            try {
                $bp = $this->storage->loadProcess($name);
            } catch (Exception $e) {
                $this->add(new BpDashboardFullscreenTile(
                    (new BpConfig())->setName($name),
                    $title,
                    sprintf(mt('businessprocess', 'File %s has faulty config'), $name . '.conf')
                ));

                continue;
            }

            $bp->applyDbStates();

            $this->add(new BpDashboardFullscreenTile(
                $bp,
                $title,
                $meta->get('Description')
            ));
        }
    }

    /**
     * @param Storage $storage
     * @return static
     */
    public static function create(Storage $storage)
    {
        return new static($storage);
    }
}
