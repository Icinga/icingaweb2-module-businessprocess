<?php

namespace Icinga\Module\Businessprocess\Renderer;

use Icinga\Exception\ProgrammingError;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\MonitoredNode;
use Icinga\Module\Businessprocess\Node;
use Icinga\Module\Businessprocess\Web\Url;
use ipl\Html\BaseHtmlElement;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;

abstract class Renderer extends HtmlDocument
{
    /** @var BpConfig */
    protected $config;

    /** @var BpNode */
    protected $parent;

    /** @var bool Administrative actions are hidden unless unlocked */
    protected $locked = true;

    /** @var Url */
    protected $url;

    /** @var Url */
    protected $baseUrl;

    /** @var array */
    protected $path = array();

    /** @var bool */
    protected $isBreadcrumb = false;

    /**
     * Renderer constructor.
     *
     * @param BpConfig $config
     * @param BpNode|null $parent
     */
    public function __construct(BpConfig $config, BpNode $parent = null)
    {
        $this->config = $config;
        $this->parent = $parent;
    }

    /**
     * @return BpConfig
     */
    public function getBusinessProcess()
    {
        return $this->config;
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

    public function rendersImportedNode()
    {
        return $this->parent !== null && $this->parent->getBpConfig()->getName() !== $this->config->getName();
    }

    public function setParentNode(BpNode $node)
    {
        $this->parent = $node;
        return $this;
    }

    /**
     * @return BpNode
     */
    public function getParentNode()
    {
        return $this->parent;
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
     * @return BpNode[]
     */
    public function getChildNodes()
    {
        if ($this->wantsRootNodes()) {
            return $this->config->getRootNodes();
        } else {
            return $this->parent->getChildren();
        }
    }

    /**
     * @return int
     */
    public function countChildNodes()
    {
        if ($this->wantsRootNodes()) {
            return $this->config->countChildren();
        } else {
            return $this->parent->countChildren();
        }
    }

    /**
     * @param $summary
     * @return BaseHtmlElement
     */
    public function renderStateBadges($summary)
    {
        $elements = [];

        foreach ($summary as $state => $cnt) {
            if ($cnt === 0
                || $state === 'OK'
                || $state === 'UP'
            ) {
                continue;
            }

            $elements[] = Html::tag(
                'span',
                [
                    'class' => [
                        'badge',
                        'badge-' . strtolower($state)
                    ],
                    // TODO: We should translate this in this module
                    'title' => mt('monitoring', $state)
                ],
                $cnt
            );
        }

        if (!empty($elements)) {
            $container = Html::tag('div', ['class' => 'badges']);
            foreach ($elements as $element) {
                $container->add($element);
            }

            return $container;
        }
        return null;
    }

    public function getNodeClasses(Node $node)
    {
        if ($node->isMissing()) {
            $classes = array('missing');
        } else {
            if ($node->isEmpty() && ! $node instanceof MonitoredNode) {
                $classes = array('empty');
            } else {
                $classes = array(
                    strtolower($node->getStateName())
                );
            }
            if ($node->hasMissingChildren()) {
                $classes[] = 'missing-children';
            }
        }

        if ($node->isHandled()) {
            $classes[] = 'handled';
        }

        if ($node instanceof BpNode) {
            $classes[] = 'process-node';
        } else {
            $classes[] = 'monitored-node';
        }
        // TODO: problem?
        return $classes;
    }

    /**
     * Return the url to the given node's source configuration
     *
     * @param   BpNode  $node
     *
     * @return  Url
     */
    public function getSourceUrl(BpNode $node)
    {
        if ($node instanceof ImportedNode) {
            $name = $node->getNodeName();
            $paths = $node->getBpConfig()->getBpNode($name)->getPaths();
        } else {
            $name = $node->getName();
            $paths = $node->getPaths();
        }

        $url = clone $this->getUrl();
        $url->setParams([
            'config'    => $node->getBpConfig()->getName(),
            'node'      => $name
        ]);
        // This depends on the fact that the node's root path is the last element in $paths
        $url->getParams()->addValues('path', array_slice(array_pop($paths), 0, -1));
        if (! $this->isLocked()) {
            $url->getParams()->add('unlocked', true);
        }

        return $url;
    }

    /**
     * @param Node $node
     * @param $path
     * @return string
     */
    public function getId(Node $node, $path)
    {
        return md5((empty($path) ? '' : implode(';', $path)) . $node->getName());
    }

    public function setPath(array $path)
    {
        $this->path = $path;
        return $this;
    }

    /**
     * @return array
     */
    public function getPath()
    {
        return $this->path;
    }

    public function getCurrentPath()
    {
        $path = $this->getPath();
        if ($this->rendersSubNode()) {
            $path[] = $this->rendersImportedNode()
                ? $this->parent->getIdentifier()
                : $this->parent->getName();
        }

        return $path;
    }

    /**
     * @param Url $url
     * @return $this
     */
    public function setUrl(Url $url)
    {
        $this->url = $url->without(array(
            'action',
            'deletenode',
            'deleteparent',
            'editnode',
            'simulationnode',
            'view'
        ));
        $this->setBaseUrl($this->url);
        return $this;
    }

    /**
     * @param Url $url
     * @return $this
     */
    protected function setBaseUrl(Url $url)
    {
        $this->baseUrl = $url->without(array('node', 'path'));
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
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
     * TODO: Get rid of this
     *
     * @return $this
     */
    public function setIsBreadcrumb()
    {
        $this->isBreadcrumb = true;
        return $this;
    }

    public function isBreadcrumb()
    {
        return $this->isBreadcrumb;
    }

    protected function createUnboundParent(BpConfig $bp)
    {
        return $bp->getNode('__unbound__');
    }

    /**
     * Just to be on the safe side
     */
    public function __destruct()
    {
        unset($this->parent);
        unset($this->config);
    }
}
