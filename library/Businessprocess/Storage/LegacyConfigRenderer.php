<?php

namespace Icinga\Module\Businessprocess\Storage;

use Icinga\Module\Businessprocess\BpNode;
use Icinga\Module\Businessprocess\BpConfig;
use Icinga\Module\Businessprocess\ImportedNode;

class LegacyConfigRenderer
{
    /** @var array */
    protected $renderedNodes;

    /** @var BpConfig */
    protected $config;

    /**
     * LecagyConfigRenderer constructor
     *
     * @param BpConfig $config
     */
    public function __construct(BpConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function render()
    {
        return $this->renderHeader() . $this->renderNodes();
    }

    /**
     * @param BpConfig $config
     * @return mixed
     */
    public static function renderConfig(BpConfig $config)
    {
        $renderer = new static($config);
        return $renderer->render();
    }

    /**
     * @return string
     */
    public function renderHeader()
    {
        $str = "### Business Process Config File ###\n#\n";

        $meta = $this->config->getMetadata();
        foreach ($meta->getProperties() as $key => $value) {
            if ($value === null) {
                continue;
            }

            $lineNum = 1;
            $spaces = str_repeat(' ', strlen(sprintf("%-15s :", $key)));

            foreach (preg_split('/\r?\n/', $value) as $line) {
                if ($lineNum === 1) {
                    $str .= sprintf("# %-15s : %s\n", $key, $line);
                } else {
                    $str .= sprintf("# %s %s\n", $spaces, $line);
                }

                $lineNum++;
            }
        }

        $str .= "#\n###################################\n\n";

        return $str;
    }

    /**
     * @return string
     */
    public function renderNodes()
    {
        $this->renderedNodes = array();

        $config = $this->config;
        $str = '';

        foreach ($config->getRootNodes() as $node) {
            $str .= $this->requireRenderedBpNode($node);
        }

        foreach ($config->getUnboundNodes() as $name => $node) {
            $str .= $this->requireRenderedBpNode($node);
        }

        return $str . "\n";
    }

    /**
     * Rendered node definition, empty string if already rendered
     *
     * @param BpNode $node
     *
     * @return string
     */
    protected function requireRenderedBpNode(BpNode $node)
    {
        $name = $node->getName();

        if (array_key_exists($name, $this->renderedNodes)) {
            return '';
        } else {
            $this->renderedNodes[$name] = true;
            return $this->renderBpNode($node);
        }
    }

    /**
     * @param BpNode $node
     * @return string
     */
    protected function renderBpNode(BpNode $node)
    {
        $name = $node->getName();
        // Doing this before rendering children allows us to store loops
        $cfg = '';

        foreach ($node->getChildBpNodes() as $name => $child) {
            if ($child instanceof ImportedNode) {
                continue;
            }

            $cfg .= $this->requireRenderedBpNode($child) . "\n";
        }

        $cfg .= static::renderSingleBpNode($node);

        return $cfg;
    }

    /**
     * @param BpNode $node
     * @return string
     */
    public static function renderEqualSign(BpNode $node)
    {
        $op = $node->getOperator();
        if (is_numeric($op)) {
            return '= ' . $op . ' of:';
        } else {
            return '=';
        }
    }

    /**
     * @param BpNode $node
     * @return string
     */
    public static function renderOperator(BpNode $node)
    {
        $op = $node->getOperator();
        if (is_numeric($op)) {
            return '+';
        } else {
            return $op;
        }
    }

    /**
     * @param BpNode $node
     * @return string
     */
    public static function renderSingleBpNode(BpNode $node)
    {
        return static::renderExpression($node)
            . static::renderStateOverrides($node)
            . static::renderDisplay($node)
            . static::renderInfoUrl($node);
    }

    /**
     * @param BpNode $node
     * @return string
     */
    public static function renderExpression(BpNode $node)
    {
        return sprintf(
            "%s %s %s\n",
            $node->getName(),
            static::renderEqualSign($node),
            static::renderChildNames($node)
        );
    }

    /**
     * @param BpNode $node
     * @return string
     */
    public static function renderChildNames(BpNode $node)
    {
        $op = static::renderOperator($node);
        $children = $node->getChildNames();
        $str = implode(' ' . $op . ' ', array_map(function ($val) {
            return preg_replace('~([\|\+&\!\%\^])~', '\\\\$1', $val);
        }, $children));

        if ((count($children) < 2) && $op !== '&') {
            return $op . ' ' . $str;
        } else {
            return $str;
        }
    }

    /**
     * @param BpNode $node
     * @return string
     */
    public static function renderDisplay(BpNode $node)
    {
        if ($node->hasAlias() || $node->getDisplay() > 0) {
            $prio = $node->getDisplay();
            return sprintf(
                "display %s;%s;%s\n",
                $prio,
                $node->getName(),
                $node->getAlias()
            );
        } else {
            return '';
        }
    }

    public static function renderStateOverrides(BpNode $node)
    {
        $stateOverrides = '';
        foreach ($node->getStateOverrides() as $childName => $overrideRules) {
            $overrides = [];
            foreach ($overrideRules as $from => $to) {
                $overrides[] = sprintf('%d-%d', $from, $to);
            }

            if (! empty($overrides)) {
                $stateOverrides .= '!' . $childName . '|' . join(',', $overrides);
            }
        }

        if (! $stateOverrides) {
            return '';
        }

        return 'state_overrides ' . $node->getName() . $stateOverrides . "\n";
    }

    /**
     * @param BpNode $node
     * @return string
     */
    public static function renderInfoUrl(BpNode $node)
    {
        if ($node->hasInfoUrl()) {
            return sprintf(
                "info_url %s;%s\n",
                $node->getName(),
                $node->getInfoUrl()
            );
        } else {
            return '';
        }
    }
}
