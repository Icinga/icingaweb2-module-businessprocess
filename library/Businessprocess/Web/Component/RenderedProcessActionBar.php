<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Web\Url;
use ipl\Html\Html;

class RenderedProcessActionBar extends ActionBar
{
    public function __construct(BpConfig $config, Renderer $renderer, Auth $auth, Url $url)
    {
        $meta = $config->getMetadata();

        $toggle = Html::tag('div', ['class' => 'view-toggle']);
        $toggle->add(Html::tag('span', null, mt('businessprocess', 'View')));
        $toggle->add(Html::tag(
            'a',
            [
                'href'  => $url->with('mode', 'tile'),
                'title' => mt('businessprocess', 'Switch to Tile view'),
                'class' => $renderer instanceof TreeRenderer ? '' : 'active'
            ],
            Html::tag('i', ['class' => 'icon icon-dashboard'])
        ));
        $toggle->add(Html::tag(
            'a',
            [
                'href'  => $url->with('mode', 'tree'),
                'title' => mt('businessprocess', 'Switch to Tree view'),
                'class' => $renderer instanceof TreeRenderer ? 'active' : ''
            ],
            Html::tag('i', ['class' => 'icon icon-sitemap'])
        ));
        $this->add($toggle);

        $this->add(Html::tag(
            'a',
            [
                'data-base-target' => '_main',
                'href'  => $url->with('showFullscreen', true),
                'title' => mt('businessprocess', 'Switch to fullscreen mode'),
                'class' => 'icon-resize-full-alt'
            ],
            mt('businessprocess', 'Fullscreen')
        ));

        $hasChanges = $config->hasSimulations() || $config->hasBeenChanged();

        if ($renderer->isLocked()) {
            $this->add(Html::tag(
                'a',
                [
                    'href'  => $url->with('unlocked', true),
                    'title' => mt('businessprocess', 'Click to unlock editing for this process'),
                    'class' => 'icon-lock'
                ],
                mt('businessprocess', 'Unlock Editing')
            ));
        } elseif (! $hasChanges) {
            $this->add(Html::tag(
                'a',
                [
                    'href'  => $url->without('unlocked')->without('action'),
                    'title' => mt('businessprocess', 'Click to lock editing for this process'),
                    'class' => 'icon-lock-open'
                ],
                mt('businessprocess', 'Lock Editing')
            ));
        }

        if ($renderer->wantsRootNodes() && (($hasChanges || (! $renderer->isLocked())) && $meta->canModify())) {
            $this->add(Html::tag(
                'a',
                [
                    'data-base-target' => '_next',
                    'href'  => Url::fromPath('businessprocess/process/config', $this->currentProcessParams($url)),
                    'title' => mt('businessprocess', 'Modify this process'),
                    'class' => 'icon-wrench'
                ],
                mt('businessprocess', 'Config')
            ));
        }

        if (($hasChanges || (! $renderer->isLocked())) && $meta->canModify()) {
            $this->add(Html::tag(
                'a',
                [
                    'href'  => $url->with('action', 'add'),
                    'title' => mt('businessprocess', 'Add a new business process node'),
                    'class' => 'icon-plus button-link'
                ],
                mt('businessprocess', 'Add Process')
            ));
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
