<?php

namespace App\Support;

/**
 * Amounts in step texts that clients scale with the chosen servings. An
 * amount is a number in curly braces, e.g. "{250} g flour".
 */
final class StepAmounts
{
    private const string MARKER = '~\{(\d+(?:[.,]\d+)?|\d+/\d+)\}~';

    /**
     * Whether the text marks at least one amount.
     */
    public static function has(string $text): bool
    {
        return preg_match(self::MARKER, $text) === 1;
    }

    /**
     * The text as written without markers.
     */
    public static function strip(string $text): string
    {
        return (string) preg_replace(self::MARKER, '$1', $text);
    }

    /**
     * Whether the marked text only adds markers to the original and
     * changes nothing else.
     */
    public static function marks(string $marked, string $original): bool
    {
        return ! str_contains(self::strip($marked), '{') && self::strip($marked) === self::strip($original);
    }
}
