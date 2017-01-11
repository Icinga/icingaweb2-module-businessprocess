<?php

namespace Icinga\Module\Businessprocess\Storage;

use Diff;
use Diff_Renderer_Html_Inline;
use Diff_Renderer_Html_SideBySide;
use Diff_Renderer_Text_Context;
use Diff_Renderer_Text_Unified;
use Icinga\Module\Businessprocess\Html\Renderable;

class ConfigDiff implements Renderable
{
    protected $a;

    protected $b;

    protected $diff;
    protected $opcodes;

    protected function __construct($a, $b)
    {
        $this->requireVendorLib('Diff.php');

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
        $this->diff = new Diff($this->a, $this->b, $options);
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
        $this->requireVendorLib('Diff/Renderer/Html/SideBySide.php');
        $renderer = new Diff_Renderer_Html_SideBySide;
        return $this->diff->render($renderer);
    }

    public function renderHtmlInline()
    {
        $this->requireVendorLib('Diff/Renderer/Html/Inline.php');
        $renderer = new Diff_Renderer_Html_Inline;
        return $this->diff->render($renderer);
    }

    public function renderTextContext()
    {
        $this->requireVendorLib('Diff/Renderer/Text/Context.php');
        $renderer = new Diff_Renderer_Text_Context;
        return $this->diff->render($renderer);
    }

    public function renderTextUnified()
    {
        $this->requireVendorLib('Diff/Renderer/Text/Unified.php');
        $renderer = new Diff_Renderer_Text_Unified;
        return $this->diff->render($renderer);
    }

    protected function requireVendorLib($file)
    {
        require_once dirname(dirname(__DIR__)) . '/vendor/php-diff/lib/' . $file;
    }

    public static function create($a, $b)
    {
        $diff = new static($a, $b);
        return $diff;
    }
}
