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

            $parents = [];
            if ($config->hasNode($name)) {
                foreach ($config->getNode($name)->getPaths() as $path) {
                    array_pop($path);  // Remove the monitored node
                    $immediateParentName = array_pop($path);  // The directly affected process
                    $parents[] = [$config->getNode($immediateParentName), $path];
                }
            }

            $askedConfigs = [];
            foreach ($config->getImportedNodes() as $importedNode) {
                $importedConfig = $importedNode->getBpConfig();

                if (isset($askedConfigs[$importedConfig->getName()])) {
                    continue;
                } else {
                    $askedConfigs[$importedConfig->getName()] = true;
                }

                if ($importedConfig->hasNode($name)) {
                    $node = $importedConfig->getNode($name);
                    $nativePaths = $node->getPaths();

                    do {
                        $path = array_pop($nativePaths);
                        $importedNodePos = array_search($importedNode->getIdentifier(), $path, true);
                        if ($importedNodePos !== false) {
                            array_pop($path);  // Remove the monitored node
                            $immediateParentName = array_pop($path);  // The directly affected process
                            $importedPath = array_slice($path, $importedNodePos + 1);
                            foreach ($importedNode->getPaths() as $targetPath) {
                                if ($targetPath[count($targetPath) - 1] === $immediateParentName) {
                                    array_pop($targetPath);
                                    $parent = $importedNode;
                                } else {
                                    $parent = $importedConfig->getNode($immediateParentName);
                                }

                                $parents[] = [$parent, array_merge($targetPath, $importedPath)];
                            }

                            // We may get multiple native paths. Though, the right hand of the path is everywhere the
                            // same and the left hand not of any interest since that's where the import location is.
                            break;  // So, once we've found a match, we're done here (Otherwise we'll get duplicates)
                        }
                    } while (! empty($nativePaths));
                }
            }

            if (empty($parents)) {
                continue;
            }

            MonitoringState::apply($config);
            $config->applySimulation($simulation);

            foreach ($parents as $parentAndPath) {
                $renderer = (new TileRenderer($config, array_shift($parentAndPath)))
                    ->setUrl(Url::fromPath('businessprocess/process/show', ['config' => $configName]))
                    ->setPath(array_shift($parentAndPath));

                $bc = Breadcrumb::create($renderer);
                $bc->getAttributes()->set('data-base-target', '_next');
                $content->add($bc);
            }
        }

        if ($content->isEmpty()) {
            $content->add($this->translate('No impact detected. Is this node part of a business process?'));
        }
    }
}
