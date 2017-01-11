<?php

namespace Icinga\Module\Businessprocess\Web\Component;

use Icinga\Authentication\Auth;
use Icinga\Module\Businessprocess\Html\BaseElement;
use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\HtmlTag;
use Icinga\Module\Businessprocess\Storage\Storage;

class Dashboard extends BaseElement
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
            HtmlTag::h1($this->translate('Welcome to your Business Process Overview'))
        );
        $this->add(
            HtmlTag::p(
                $this->translate(
                    'From here you can reach all your defined Business Process'
                    . ' configurations, create new or modify existing ones'
                )
            )
        );
        if ($auth->hasPermission('businessprocess/create')) {
            $this->add(
                new DashboardAction(
                    $this->translate('Create'),
                    $this->translate('Create a new Business Process configuration'),
                    'plus',
                    'businessprocess/process/create',
                    null,
                    array('class' => 'addnew')
                )
            )->add(
                new DashboardAction(
                    $this->translate('Upload'),
                    $this->translate('Upload an existing Business Process configuration'),
                    'upload',
                    'businessprocess/process/upload',
                    null,
                    array('class' => 'addnew')
                )
            );
        } elseif (empty($processes)) {
            $this->addContent(
                Container::create()
                    ->add(HtmlTag::h1($this->translate('Not available')))
                    ->add(HtmlTag::p($this->translate('No Business Process has been defined for you')))
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
            $this->add(new DashboardAction(
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
