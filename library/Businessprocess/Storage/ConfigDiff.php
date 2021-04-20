<?php

namespace Icinga\Module\Businessprocess\Storage;

use ipl\Html\ValidHtml;
use Jfcherng\Diff\Differ;
use Jfcherng\Diff\Factory\RendererFactory;

class ConfigDiff implements ValidHtml
{
    protected $a;

    protected $b;

    protected $diff;
    protected $opcodes;

    protected function __construct($a, $b)
    {
        if (empty($a)) {
            $this->a = array();
        } else {
            $this->a = explode("\n", (string) $a);
        }

        if (empty($b)) {
            $this->b = array();
        } else {
            $this->b = explode("\n", (string) $b);
        }

        $options = array(
            'context' => 5,
            // 'ignoreWhitespace' => true,
            // 'ignoreCase' => true,
        );
        $this->diff = new Differ($this->a, $this->b, $options);
    }

    /**
     * @return string
     */
    public function render()
    {
        return $this->renderHtmlSideBySide();
    }

    public function renderHtmlSideBySide()
    {
        $renderer = RendererFactory::make('SideBySide');
        return $renderer->render($this->diff);
    }

    public function renderHtmlInline()
    {
        $renderer = RendererFactory::make('Inline');
        return $renderer->render($this->diff);
    }

    public function renderTextContext()
    {
        $renderer = RendererFactory::make('Context');
        return $renderer->render($this->diff);
    }

    public function renderTextUnified()
    {
        $renderer = RendererFactory::make('Unified');
        return $renderer->render($this->diff);
    }

    public static function create($a, $b)
    {
        $diff = new static($a, $b);
        return $diff;
    }
}
