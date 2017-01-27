<?php

namespace Icinga\Module\Businessprocess\Controllers;

use Icinga\Module\Businessprocess\Renderer\Breadcrumb;
use Icinga\Module\Businessprocess\Renderer\TileRenderer;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Businessprocess\Web\Url;

class NodeController extends Controller
{
    public function impactAction()
    {
        $this->setAutorefreshInterval(10);
        $content = $this->content();
        $this->controls()->add(
            $this->singleTab($this->translate('Node Impact'))
        );
        $name = $this->params->get('name');
        $this->addTitle($this->translate('Business Impact (%s)'), $name);

        $simulation = Simulation::fromSession($this->session());
        foreach ($this->storage()->listProcessNames() as $configName) {
            $config = $this->storage()->loadProcess($configName);

            // TODO: Fix issues with children, they do not exist unless resolved :-/
            // This is a workaround:
            foreach ($config->getRootNodes() as $node) {
                $node->getState();
            }
            foreach ($config->getRootNodes() as $node) {
                $node->clearState();
            }

            if (! $config->hasNode($name)) {
                continue;
            }

            MonitoringState::apply($config);
            $config->applySimulation($simulation);

            foreach ($config->getNode($name)->getPaths() as $path) {
                array_pop($path);
                $node = array_pop($path);
                $renderer = new TileRenderer($config, $config->getNode($node));
                $renderer->setUrl(
                    Url::fromPath(
                        'businessprocess/process/show',
                        array('config' => $configName)
                    )
                )->setPath($path);

                $bc = Breadcrumb::create($renderer);
                $bc->attributes()->set('data-base-target', '_next');
                $content->add($bc);
            }
        }
    }
}
