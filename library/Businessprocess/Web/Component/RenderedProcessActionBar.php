<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\Renderer\Renderer;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Web\Url;
use ipl\Html\Html;
use ipl\Web\Widget\Icon;

class RenderedProcessActionBar extends ActionBar
{
    public function __construct(BpConfig $config, Renderer $renderer, Auth $auth, Url $url)
    {
        $meta = $config->getMetadata();

        if ($renderer instanceof TreeRenderer) {
            $link = Html::tag(
                'a',
                [
                    'href'  => $url->with('mode', 'tile'),
                    'title' => mt('businessprocess', 'Switch to Tile view')
                ]
            );
        } else {
            $link = Html::tag(
                'a',
                [
                    'href'  => $url->with('mode', 'tree'),
                    'title' => mt('businessprocess', 'Switch to Tree view')
                ]
            );
        }

        $link->add([
            new Icon('grip', ['class' => $renderer instanceof TreeRenderer ? null : 'active']),
            new Icon('sitemap', ['class' => $renderer instanceof TreeRenderer ? 'active' : null])
        ]);

        $this->add(
            Html::tag('div', ['class' => 'view-toggle'])
                ->add(Html::tag('span', null, mt('businessprocess', 'View')))
                ->add($link)
        );

        $this->add(Html::tag(
            'a',
            [
                'data-base-target' => '_main',
                'href'  => $url->with('showFullscreen', true),
                'title' => mt('businessprocess', 'Switch to fullscreen mode'),
            ],
            [
                new Icon('maximize'),
                mt('businessprocess', 'Fullscreen')
            ]
        ));

        $hasChanges = $config->hasSimulations() || $config->hasBeenChanged();

        if ($renderer->isLocked()) {
            if (! $renderer->wantsRootNodes() && $renderer->rendersImportedNode()) {
                $span = Html::tag('span', [
                    'class' => 'disabled',
                    'title' => mt(
                        'businessprocess',
                        'Imported processes can only be changed in their original configuration'
                    )
                ]);
                $span->add([new Icon('lock'), mt('businessprocess', 'Editing Locked')]);
                $this->add($span);
            } else {
                $this->add(Html::tag(
                    'a',
                    [
                        'href'  => $url->with('unlocked', true),
                        'title' => mt('businessprocess', 'Click to unlock editing for this process'),
                    ],
                    [
                        new Icon('lock'),
                        mt('businessprocess', 'Unlock Editing')
                    ]
                ));
            }
        } elseif (! $hasChanges) {
            $this->add(Html::tag(
                'a',
                [
                    'href'  => $url->without('unlocked')->without('action'),
                    'title' => mt('businessprocess', 'Click to lock editing for this process'),
                ],
                [
                    new Icon('lock-open'),
                    mt('businessprocess', 'Lock Editing')
                ]
            ));
        }

        if (($hasChanges || ! $renderer->isLocked()) && $meta->canModify()) {
            if ($renderer->wantsRootNodes()) {
                $this->add(Html::tag(
                    'a',
                    [
                        'data-base-target' => '_next',
                        'href'  => Url::fromPath('businessprocess/process/config', $this->currentProcessParams($url)),
                        'title' => mt('businessprocess', 'Modify this process'),
                    ],
                    [
                        new Icon('wrench'),
                        mt('businessprocess', 'Config')
                    ]
                ));
            } else {
                $this->add(Html::tag(
                    'a',
                    [
                        'href'  => $url->with([
                            'action'    => 'edit',
                            'editnode'  => $url->getParam('node')
                        ])->getAbsoluteUrl(),
                        'title' => mt('businessprocess', 'Modify this process'),
                    ],
                    [
                        new Icon('wrench'),
                        mt('businessprocess', 'Config')
                    ]
                ));
            }
        }

        if (($hasChanges || (! $renderer->isLocked())) && $meta->canModify()) {
            $this->add(Html::tag(
                'a',
                [
                    'href'  => $url->with('action', 'add'),
                    'title' => mt('businessprocess', 'Add a new business process node'),
                    'class' => 'button-link'
                ],
                [
                    new Icon('plus'),
                    mt('businessprocess', 'Add Node')
                ]
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
