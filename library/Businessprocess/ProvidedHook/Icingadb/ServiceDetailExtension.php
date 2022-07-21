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
    /** @var LegacyStorage */
    private $storage;

    /** @var string */
    private $commandName;

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

        $bpName = $service->customvars['icingacli_businessprocess_config'] ?? null;
        if (! $bpName) {
            $bpName = key($this->storage->listProcessNames());
        }

        $nodeName = $service->customvars['icingacli_businessprocess_process'] ?? null;
        if (! $nodeName) {
            return HtmlString::create('');
        }

        $bp = $this->storage->loadProcess($bpName);
        $node = $bp->getBpNode($nodeName);

        IcingaDbState::apply($bp);

        if ($service->customvars['icingaweb_businessprocess_as_tree'] ?? false) {
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
