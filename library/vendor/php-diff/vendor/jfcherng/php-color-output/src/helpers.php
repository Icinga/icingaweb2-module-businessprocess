<?php

declare(strict_types=1);

if (!\function_exists('str_cli_color')) {
    /**
     * Make a string colorful.
     *
     * A global alias to \Jfcherng\Utility\CliColor::color.
     *
     * @param string       $str       the string
     * @param array|string $colors    the colors
     * @param bool         $autoReset automatically reset at the end of the string?
     *
     * @return string the colored string
     */
    function str_cli_color(string $str, $colors = [], bool $autoReset = true): string
    {
        return \Jfcherng\Utility\CliColor::color($str, $colors, $autoReset);
    }
}

if (!\function_exists('str_cli_nocolor')) {
    /**
     * Remove all colors from a string.
     *
     * A global alias to \Jfcherng\Utility\CliColor::noColor
     *
     * @param string $str the string
     *
     * @return string the string without colors
     */
    function str_cli_nocolor(string $str): string
    {
        return \Jfcherng\Utility\CliColor::noColor($str);
    }
}
