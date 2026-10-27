<?php

// SPDX-FileCopyrightText: 2026 Icinga GmbH <https://icinga.com>
// SPDX-License-Identifier: GPL-3.0-or-later

namespace Icinga\Module\Businessprocess\ProvidedHook\Dashboards;

use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\ImportedNode;
use Icinga\Module\Businessprocess\Storage\LegacyStorage;
use Icinga\Module\Dashboards\Hook\DashletControls;
use Icinga\Module\Dashboards\Hook\DashletHook;
use ipl\Web\Url;
use Throwable;

/**
 * A business process, or a node below its root, as a dashlet
 *
 * Embeds `businessprocess/process/show` in compact mode, the breadcrumb optional. Editing (`unlocked`,
 * simulations, `dismiss*`) and the state type are not offered: a dashlet shows what the process is configured to show.
 *
 * The node is chosen one level at a time, `node1` below the process, `node2` below that, and so on. Each level
 * is declared from the value of the one before it, which is why they carry `autosubmit`.
 */
class ProcessView extends DashletHook
{
    private const ROUTE = 'businessprocess/process/show';

    private const MIN_SIZE = 1;

    private const MAX_SIZE = 7;

    private const DEFAULT_SIZE = 4;

    /** @var array<string, ?BpConfig> Parsed once per request: `configure()` is called per render */
    private $processes = [];

    /** @var ?array<string, string> The readable processes, for the same reason */
    private $names;

    public function getName(): string
    {
        return 'process-view';
    }

    public function getLabel(): string
    {
        return $this->translateInDomain('businessprocess', 'Process View');
    }

    public function getDescription(): string
    {
        return $this->translateInDomain('businessprocess', 'A business process or one of its nodes.');
    }

    public function configure(DashletControls $controls): void
    {
        $config = (string) $controls->value('config');
        $process = $this->process($config);

        $controls->addElement('select', 'config', [
            'label'      => $this->translateInDomain('businessprocess', 'Process'),
            'autosubmit' => true,
            'options'    => ['' => $this->translateInDomain('businessprocess', 'Please choose')]
                + ($this->names = $this->names ?? LegacyStorage::getInstance()->listProcesses()),
        ]);

        $level = 0;

        foreach ($this->walk($process, [$controls, 'value']) as $nodes) {
            $level++;

            $options = ['' => $level === 1
                ? $this->translateInDomain('businessprocess', 'The whole process')
                : $this->translateInDomain('businessprocess', 'The whole node above')];

            foreach ($nodes as $name => $node) {
                $options[$name] = $node->getAlias();
            }

            $controls->addElement('select', "node$level", [
                'label'      => $level === 1
                    ? $this->translateInDomain('businessprocess', 'Node')
                    : $this->translateInDomain('businessprocess', 'Sub-node'),
                'autosubmit' => true,
                'options'    => $options,
            ]);
        }

        $controls->addElement('checkbox', 'breadcrumb', [
            'label' => $this->translateInDomain('businessprocess', 'Show Breadcrumb'),
            'value' => 'y',
        ]);

        $controls->viewMode([
            'tile' => $this->translateInDomain('businessprocess', 'Tiles'),
            'tree' => $this->translateInDomain('businessprocess', 'Tree'),
        ], 'tile', 'mode');

        // Ignored in the tree
        $controls->addElement('input', 'tile_size', [
            'label'    => $this->translateInDomain('businessprocess', 'Tile Size'),
            'type'     => 'range',
            'min'      => self::MIN_SIZE,
            'max'      => self::MAX_SIZE,
            'step'     => 1,
            'value'    => (string) self::DEFAULT_SIZE,
            'minLabel' => $this->translateInDomain('businessprocess', 'Small'),
            'maxLabel' => $this->translateInDomain('businessprocess', 'Large'),
        ]);

        $sort = [
            'display_name asc' => $this->translateInDomain('businessprocess', 'Name'),
            'state desc'       => $this->translateInDomain('businessprocess', 'State'),
        ];
        $manual = $process !== null && $process->getMetadata()->isManuallyOrdered();

        if ($manual) {
            $sort['manual asc'] = $this->translateInDomain('businessprocess', 'Manual');
        }

        $controls->sortBy($sort, $manual ? 'manual asc' : 'display_name asc');
    }

    public function getRoute(array $settings): ?Url
    {
        $config = $settings['config'] ?? '';

        if ($config === '') {
            return null;
        }

        $chain = [];

        foreach ($this->walk($this->process($config), function (string $name) use ($settings) {
            return $settings[$name] ?? null;
        }) as $level => $nodes) {
            $chosen = $settings['node' . ($level + 1)] ?? '';

            if (! isset($nodes[$chosen])) {
                break;
            }

            $chain[] = $chosen;
        }

        $url = Url::fromPath(self::ROUTE, ['config' => $config]);

        $size = (int) ($settings['tile_size'] ?? self::DEFAULT_SIZE);

        if ($size >= self::MIN_SIZE && $size <= self::MAX_SIZE) {
            $url->setParam('tileSize', $size);
        }

        if (($settings['breadcrumb'] ?? 'y') === 'n') {
            $url->setParam('hideBreadcrumb', true);
        }

        if ($chain) {
            $url->setParam('node', array_pop($chain));

            if ($chain) {
                $url->getParams()->addValues('path', $chain);
            }
        }

        return $url;
    }

    public function capture(Url $url): ?array
    {
        if ($url->getPath() !== self::ROUTE) {
            return null;
        }

        $config = (string) $url->shift('config');
        $node = $url->shift('node');
        $path = $url->getParams()->getValues('path');
        $hideBreadcrumb = $url->shift('hideBreadcrumb');
        $size = (int) $url->shift('tileSize');

        $url->remove('path');

        if ($this->process($config) === null) {
            return null;
        }

        $chain = $node === null ? [] : array_merge($path, [$node]);
        $claim = ['config' => $config];

        if ($hideBreadcrumb) {
            $claim['breadcrumb'] = 'n';
        }

        if ($size >= self::MIN_SIZE && $size <= self::MAX_SIZE) {
            $claim['tile_size'] = (string) $size;
        }

        foreach ($chain as $i => $name) {
            $claim['node' . ($i + 1)] = $name;
        }

        // A path that does not walk is a node of an imported process, or an edited address
        $walked = 0;

        foreach ($this->walk($this->process($config), function (string $name) use ($claim) {
            return $claim[$name] ?? null;
        }) as $level => $nodes) {
            if (! isset($nodes[$claim['node' . ($level + 1)] ?? ''])) {
                break;
            }

            $walked++;
        }

        return $walked === count($chain) ? $claim : null;
    }

    /**
     * Walk the chosen levels below a process
     *
     * Yields the nodes each level offers, up to and including the first level without a valid choice.
     *
     * @param ?BpConfig $process
     * @param callable $value Gets the raw value of a control by name, null where it has none
     *
     * @return \Generator<int, array<string, BpNode>>
     */
    private function walk(?BpConfig $process, callable $value): \Generator
    {
        if ($process === null) {
            return;
        }

        $nodes = $this->bpNodes($process->getRootNodes());
        $level = 0;

        while ($nodes) {
            yield $level => $nodes;

            $level++;
            $chosen = (string) $value("node$level");

            if (! isset($nodes[$chosen])) {
                return;
            }

            $nodes = $this->bpNodes($nodes[$chosen]->getChildren());
        }
    }

    /**
     * Keep the nodes a level can offer: this process's own business process nodes, not hosts, services or
     * another process's
     *
     * @param array<string, mixed> $nodes
     *
     * @return array<string, BpNode>
     */
    private function bpNodes(array $nodes): array
    {
        return array_filter($nodes, function ($node) {
            return $node instanceof BpNode && ! $node instanceof ImportedNode;
        });
    }

    private function process(string $name): ?BpConfig
    {
        if ($name === '') {
            return null;
        }

        if (! array_key_exists($name, $this->processes)) {
            $storage = LegacyStorage::getInstance();

            try {
                $this->processes[$name] = $storage->hasProcess($name) ? $storage->loadProcess($name) : null;
            } catch (Throwable $e) {
                $this->processes[$name] = null;
            }
        }

        return $this->processes[$name];
    }
}
