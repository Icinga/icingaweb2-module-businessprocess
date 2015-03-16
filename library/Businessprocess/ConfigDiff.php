<?php

namespace Icinga\Module\Businessprocess;

use FineDiff;

class ConfigDiff
{
    protected $a;

    protected $b;

    protected $diff;
    protected $opcodes;

    protected function __construct($a, $b)
    {
        $this->a = $a;
        $this->b = $b;
        require_once dirname(__DIR__) . '/vendor/PHP-FineDiff/finediff.php';
        $granularity = FineDiff::$paragraphGranularity; // character, word, sentence, paragraph
        $this->diff = new FineDiff($a, $b, $granularity);
    }

    public function renderHtml()
    {
        return $this->diff->renderDiffToHTML();
    }

    public function __toString()
    {
        return $this->renderHtml();
    }

    public static function create($a, $b)
    {
        $diff = new static($a, $b);
        return $diff;
    }
}
