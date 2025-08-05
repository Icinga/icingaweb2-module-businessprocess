<?php

namespace Icinga\Module\Businessprocess\ProvidedHook\Icingadb;

use Icinga\Module\Businessprocess\Renderer\TileRenderer;
use Icinga\Module\Businessprocess\Renderer\TreeRenderer;
use Icinga\Module\Businessprocess\State\IcingaDbState;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Businessprocess\Web\Url;
use Icinga\Module\Icingadb\Hook\ServiceDetailExtensionHook;
use Icinga\Module\Icingadb\Model\Service;
use ipl\Html\Html;
use ipl\Html\HtmlDocument;
use ipl\Html\HtmlString;
use ipl\Html\ValidHtml;

class ServiceDetailExtension extends ServiceDetailExtensionHook
{
    /** @var ?LegacyStorage */
    private $storage;

    /** @var string */
    private $commandName;

    /** @var string */
    private $configVar;

    /** @var string */
    private $processVar;

    /** @var string */
    private $treeVar;

    protected function init()
    {
        $this->setSection(self::GRAPH_SECTION);

        try {
            $this->storage = LegacyStorage::getInstance();
            $this->commandName = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'checkcommand_name',
                'icingacli-businessprocess'
            );
            $this->configVar = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'config_var',
                'icingacli_businessprocess_config'
            );
            $this->processVar = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'process_var',
                'icingacli_businessprocess_process'
            );
            $this->treeVar = $this->getModule()->getConfig()->get(
                'DetailviewExtension',
                'tree_var',
                'icingaweb_businessprocess_as_tree'
            );
        } catch (\Exception $e) {
            // Ignore and don't display anything
        }
    }

    public function getHtmlForObject(Service $service): ValidHtml
    {
        if (! isset($this->storage)
            || $service->checkcommand_name !== $this->commandName
        ) {
            return HtmlString::create('');
        }

        $customvars = array_merge($service->host->customvars, $service->customvars);

        $bpName = $customvars[$this->configVar] ?? null;
        if (! $bpName) {
            $bpName = key($this->storage->listProcessNames());
        }

        $nodeName = $customvars[$this->processVar] ?? null;
        if (! $nodeName) {
            return HtmlString::create('');
        }

        $bp = $this->storage->loadProcess($bpName);
        $node = $bp->getBpNode($nodeName);

        IcingaDbState::apply($bp);

        if ($customvars[$this->treeVar] ?? false) {
            $renderer = new TreeRenderer($bp, $node);
            $tag = 'ul';
        } else {
            $renderer = new TileRenderer($bp, $node);
            $tag = 'div';
        }

        $renderer->setUrl(Url::fromPath('businessprocess/process/show?config=' . $bpName . '&node=' . $nodeName));
        $renderer->ensureAssembled()->getFirst($tag)->setAttribute('data-base-target', '_next');

        return (new HtmlDocument())->addHtml(Html::tag('h2', 'Business Process'), $renderer);
    }
}
