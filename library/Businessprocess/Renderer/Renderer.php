<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BusinessProcess;
use Icinga\Module\Businessprocess\Html\Container;
use Icinga\Module\Businessprocess\Html\Element;
use Icinga\Module\Businessprocess\Html\Html;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Url;
use Icinga\Web\View;

abstract class Renderer extends Html
{
    /** @var View */
    protected $view;

    /** @var BusinessProcess */
    protected $bp;

    /** @var BpNode */
    protected $parent;

    /** @var bool Administrative actions are hidden unless unlocked */
    protected $locked = true;

    /** @var Url */
    protected $baseUrl;

    /** @var array */
    protected $path = array();

    /**
     * Renderer constructor.
     *
     * @param View $view
     * @param BusinessProcess $bp
     * @param BpNode|null $parent
     */
    public function __construct(View $view, BusinessProcess $bp, BpNode $parent = null)
    {
        $this->bp = $bp;
        $this->parent = $parent;
        $this->view = $view;
    }

    /**
     * @return BusinessProcess
     */
    public function getBusinessProcess()
    {
        return $this->bp;
    }

    /**
     * Whether this will render all root nodes
     *
     * @return bool
     */
    public function wantsRootNodes()
    {
        return $this->parent === null;
    }

    /**
     * Whether this will only render parts of given config
     *
     * @return bool
     */
    public function rendersSubNode()
    {
        return $this->parent !== null;
    }

    /**
     * @return BpNode[]
     */
    public function getParentNodes()
    {
        if ($this->wantsRootNodes()) {
            return array();
        }

        return $this->parent->getParents();
    }

    /**
     * @param $summary
     * @return Container
     */
    public function renderStateBadges($summary)
    {
        $container = Container::create(
            array('class' => 'badges')
        )/* ->renderIfEmpty(false) */;

        foreach ($summary as $state => $cnt) {
            if ($cnt === 0
                || $state === 'OK'
                || $state === 'UP'
            ) {
                continue;
            }

            $container->addContent(
                Element::create(
                    'span',
                    array(
                        'class' => array(
                            'badge',
                            'badge-' . strtolower($state)
                        ),
                        // TODO: We should translate this in this module
                        'title' => mt('monitoring', $state)
                    )
                )->setContent($cnt)
            );

        }

        return $container;
    }

    public function getNodeClasses(Node $node)
    {
        $classes = array(
            strtolower($node->getStateName())
        );

        if ($node->isHandled()) {
            $classes[] = 'handled';
        }

        if ($node instanceof BpNode) {
            $classes[] = 'process-node';
        } else {
            $classes[] = 'monitored-node';
        }

        return $classes;
    }

    public function setPath(array $path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getPath()
    {
        return $this->path;
    }

    public function getCurrentPath()
    {
        $path = $this->getPath();
        if ($this->rendersSubNode()) {
            $path[] = (string) $this->parent;
        }
        return $path;
    }

    /**
     * @param Url $url
     * @return $this
     */
    public function setBaseUrl(Url $url)
    {
        $this->baseUrl = $url->without(array('config', 'node', 'path'));
        return $this;
    }

    /**
     * @return Url
     * @throws ProgrammingError
     */
    public function getBaseUrl()
    {
        if ($this->baseUrl === null) {
            throw new ProgrammingError('Renderer has no baseUrl');
        }

        return clone($this->baseUrl);
    }

    /**
     * @return bool
     */
    public function isLocked()
    {
        return $this->locked;
    }

    /**
     * @return $this
     */
    public function lock()
    {
        $this->locked = true;
        return $this;
    }

    /**
     * @return $this
     */
    public function unlock()
    {
        $this->locked = false;
        return $this;
    }
    /**
     * Just to be on the safe side
     */
    public function __destruct()
    {
        unset($this->parent);
        unset($this->bp);
        unset($this->view);
    }
}
