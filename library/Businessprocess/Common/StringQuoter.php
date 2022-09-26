<?php

namespace Icinga\Module\Businessprocess\Common;

class StringQuoter
{
    public static function containsSpecialChars(string $string): bool
    {
        return (bool) preg_match('/[;"]/', $string);
    }

    public static function addWrapperQuotes(string $string)
    {
        // escape existing quotes
        $string = str_replace('"', '\"', $string);

        return '"' . $string  . '"';
    }

    public static function hasQuoteAtBeginning(string $string): bool
    {
        return $string[0] === '"';
    }

    public static function stringBetweenQuotes(string $string): string
    {
        if (preg_match('/^"(.*?)(?<!\\\\)"/', $string, $match)) {
            $string = $match[1];
        }

        return $string;
    }

    public static function undoQuoteEscaping(string $string) {
        return str_replace('\"', '"', $string);
    }

    public static function wrapString($string) {
        if (! self::containsSpecialChars($string)) {
            return $string;
        }

        return self::addWrapperQuotes($string);
    }

    public static function decodeChildren($children)
    {
        $res = [];
        foreach ((array)$children as $child) {
            $res[] = json_decode($child, true);
        }

        return $res;

    }
}