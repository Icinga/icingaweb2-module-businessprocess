<?php

namespace Icinga\Module\Businessprocess\Controllers;


use Exception;

use GuzzleHttp\Psr7\ServerRequest;

use Icinga\Application\Modules\Module;
use Icinga\Module\Businessprocess\Forms\AddNodeToProcessForm;
use Icinga\Module\Businessprocess\ProvidedHook\Icingadb\IcingadbSupport;
use Icinga\Module\Businessprocess\Renderer\Breadcrumb;
use Icinga\Module\Businessprocess\Renderer\TileRenderer;
use Icinga\Module\Businessprocess\Simulation;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\State\MonitoringState;
use Icinga\Module\Businessprocess\Web\Controller;
use Icinga\Module\Businessprocess\Web\Url;

use ipl\Html\Html;
use ipl\Web\Widget\Link;

use ipl\Web\Url as iplUrl;
use ipl\Web\Widget\ButtonLink;


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

        $brokenFiles = [];
        $simulation = Simulation::fromSession($this->session());
        foreach ($this->storage()->listProcessNames() as $configName) {
            try {
                $config = $this->storage()->loadProcess($configName);
            } catch (Exception $e) {
                $meta = $this->storage()->loadMetadata($configName);
                $brokenFiles[$meta->get('Title')] = $configName;
                continue;
            }

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
                    $nativePaths = $node->getPaths($config);

                    do {
                        $path = array_pop($nativePaths);
                        $importedNodePos = array_search($importedNode->getIdentifier(), $path, true);
                        if ($importedNodePos !== false) {
                            array_pop($path);  // Remove the monitored node
                            $immediateParentName = array_pop($path);  // The directly affected process
                            $importedPath = array_slice($path, $importedNodePos + 1);

                            // We may get multiple native paths. Though, only the right hand of the path
                            // is what we're interested in. The left part is not what is getting imported.
                            $antiDuplicator = join('|', $importedPath) . '|' . $immediateParentName;
                            if (isset($parents[$antiDuplicator])) {
                                continue;
                            }

                            foreach ($importedNode->getPaths($config) as $targetPath) {
                                if ($targetPath[count($targetPath) - 1] === $immediateParentName) {
                                    array_pop($targetPath);
                                    $parent = $importedNode;
                                } else {
                                    $parent = $importedConfig->getNode($immediateParentName);
                                }

                                $parents[$antiDuplicator] = [$parent, array_merge($targetPath, $importedPath)];
                            }
                        }
                    } while (! empty($nativePaths));
                }
            }

            if (empty($parents)) {
                continue;
            }

            if (Module::exists('icingadb') &&
                (! $config->getBackendName() && IcingadbSupport::useIcingaDbAsBackend())
            ) {
                IcingaDbState::apply($config);
            } else {
                MonitoringState::apply($config);
            }
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

        if (! empty($brokenFiles)) {
            $elem = Html::tag(
                'ul',
                ['class' => 'broken-files'],
                tp(
                    'The following business process has an invalid config file and therefore cannot be read:',
                    'The following business processes have invalid config files and therefore cannot be read:',
                    count($brokenFiles)
                )
            );

            foreach ($brokenFiles as $bpName => $fileName) {
                $elem->addHtml(
                    Html::tag(
                        'li',
                        new Link(
                            sprintf('%s (%s.conf)', $bpName, $fileName),
                            \ipl\Web\Url::fromPath('businessprocess/process/show', ['config' => $fileName])
                        )
                    )
                );
            }

            $content->addHtml($elem);
        }

        $content->add(
            (new ButtonLink(t('Add to process'), iplUrl::fromPath('businessprocess/node/add', ['name' => $name])))
                ->setAttribute('data-base-target', '_self')
        );
    }

    public function addAction(): void
    {
        $this->controls()->add(
            $this->singleTab($this->translate('Add Node'))
        );

        $objectName = $this->params->getRequired('name');

        $this->addTitle(sprintf(t('Add %s to process'), $objectName));

        $form = (new AddNodeToProcessForm())
            ->populate(['config' => $this->params->get('config')])
            ->setStorage($this->storage())
            ->setNodeName($objectName)
            ->setSession($this->session())
            ->handleRequest(ServerRequest::fromGlobals());

        $this->content()->add($form);
    }
}
