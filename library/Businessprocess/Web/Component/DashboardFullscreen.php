<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Storage\Storage;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;

class DashboardFullScreen extends BaseHtmlElement
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
        $processes = $storage->listProcessNames();
        if (empty($processes)) {
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
            MonitoringState::apply($bp);

            $this->add(new BpDashboardFullscreenTile(
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
