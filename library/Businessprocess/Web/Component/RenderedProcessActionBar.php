<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Html\Link;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Module\Businessprocess\Web\Url;

class RenderedProcessActionBar extends ActionBar
{
    public function __construct(BpConfig $config, Renderer $renderer, Auth $auth, Url $url)
    {
        $meta = $config->getMetadata();

        if ($renderer instanceof TreeRenderer) {
            $this->add(
                Link::create(
                    $this->translate('Tiles'),
                    $url->with('mode', 'tile'),
                    null,
                    array(
                        'class' => 'icon-dashboard',
                        'title' => $this->translate('Switch to Tile view'),
                    )
                )
            );
        } else {
            $this->add(
                Link::create(
                    $this->translate('Tree'),
                    $url->with('mode', 'tree'),
                    null,
                    array(
                        'class' => 'icon-sitemap',
                        'title' => $this->translate('Switch to Tree view'),
                    )
                )
            );
        }

        $this->add(
            Link::create(
                $this->translate('Fullscreen'),
                $url->with('showFullscreen', true),
                null,
                array(
                    'class'            => 'icon-resize-full-alt',
                    'title'            => $this->translate('Switch to fullscreen mode'),
                    'data-base-target' => '_main',
                )
            )
        );

        $hasChanges = $config->hasSimulations() || $config->hasBeenChanged();

        if ($renderer->isLocked()) {
            $this->add(
                Link::create(
                    $this->translate('Unlock'),
                    $url->with('unlocked', true),
                    null,
                    array(
                        'class' => 'icon-lock-open',
                        'title' => $this->translate('Unlock this process'),
                    )
                )
            );
        } elseif (! $hasChanges) {
            $this->add(
                Link::create(
                    $this->translate('Lock'),
                    $url->without('unlocked')->without('action'),
                    null,
                    array(
                        'class' => 'icon-lock',
                        'title' => $this->translate('Lock this process'),
                    )
                )
            );
        }

        if ($renderer->wantsRootNodes() && (
                $hasChanges || (! $renderer->isLocked()) && $meta->canModify()
        )) {
            $this->add(
                Link::create(
                    $this->translate('Config'),
                    'businessprocess/process/config',
                    $this->currentProcessParams($url),
                    array(
                        'class'            => 'icon-wrench',
                        'title'            => $this->translate('Modify this process'),
                        'data-base-target' => '_next',
                    )
                )
            );
        }
    }

    protected function currentProcessParams(Url $url)
    {
        $urlParams = $url->getParams();
        $params = array();
        foreach (array('config', 'node') as $name) {
            if ($value = $urlParams->get($name)) {
                $params[$name] = $value;
            }
        }

        return $params;
    }
}
