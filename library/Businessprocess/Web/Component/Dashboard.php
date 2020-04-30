<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\Storage;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class Dashboard extends BaseHtmlElement
{
    /** @var string */
    protected $contentSeparator = "\n";

    /** @var string */
    protected $tag = 'div';

    protected $defaultAttributes = array(
        'class' => 'overview-dashboard',
        'data-base-target' => '_next'
    );

    /** @var Auth */
    protected $auth;

    /** @var Storage */
    protected $storage;

    /**
     * Dashboard constructor.
     * @param Auth $auth
     * @param Storage $storage
     */
    protected function __construct(Auth $auth, Storage $storage)
    {
        $this->auth = $auth;
        $this->storage = $storage;
        // TODO: Auth?
        $processes = $storage->listProcessNames();
        $this->add(
            Html::tag('h1', null, mt('businessprocess', 'Welcome to your Business Process Overview'))
        );
        $this->add(Html::tag(
            'p',
            null,
            mt(
                'businessprocess',
                'From here you can reach all your defined Business Process'
                . ' configurations, create new or modify existing ones'
            )
        ));
        if ($auth->hasPermission('businessprocess/create')) {
            $this->add(
                new DashboardAction(
                    mt('businessprocess', 'Create'),
                    mt('businessprocess', 'Create a new Business Process configuration'),
                    'plus',
                    'businessprocess/process/create',
                    null,
                    array('class' => 'addnew')
                )
            )->add(
                new DashboardAction(
                    mt('businessprocess', 'Upload'),
                    mt('businessprocess', 'Upload an existing Business Process configuration'),
                    'upload',
                    'businessprocess/process/upload',
                    null,
                    array('class' => 'addnew')
                )
            );
        } elseif (empty($processes)) {
            $this->add(
                Html::tag('div')
                    ->add(Html::tag('h1', null, mt('businessprocess', 'Not available')))
                    ->add(Html::tag('p', null, mt(
                        'businessprocess',
                        'No Business Process has been defined for you'
                    )))
            );
        }

        foreach ($processes as $name) {
            $meta = $storage->loadMetadata($name);
            $title = $meta->get('Title');
            if ($title) {
                $title = sprintf('%s (%s)', $title, $name);
            } else {
                $title = $name;
            }

            $bp = $storage->loadProcess($name);
            if ($bp->getBackendName() === '_icingadb') {
                IcingaDbState::apply($bp);
            } else {
                MonitoringState::apply($bp);
            }

            $this->add(new BpDashboardTile(
                $bp,
                $title,
                $meta->get('Description'),
                'sitemap',
                'businessprocess/process/show',
                array('config' => $name)
            ));
        }
    }

    /**
     * @param Auth $auth
     * @param Storage $storage
     * @return static
     */
    public static function create(Auth $auth, Storage $storage)
    {
        return new static($auth, $storage);
    }
}
