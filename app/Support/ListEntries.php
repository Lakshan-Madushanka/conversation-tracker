<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The cleaning rules for the plain list boxes (mistakes, vocabulary and
 * phrases): pasted text is split into lines, and every line becomes one clean
 * entry.
 *
 * This mirrors `resources/js/lib/list-entries.ts`, so the browser and the
 * server always agree on what a paste becomes. The server applies the same
 * rules on save, so what is stored always matches what the learner saw.
 */
final class ListEntries
{
    /** The most entries one box can hold. */
    public const int MAX_ENTRIES = 200;

    /** The longest single entry, in characters. */
    public const int MAX_LENGTH = 1000;

    /**
     * A bullet, dash or numbering marker at the very start of a line, such as
     * "• ", "- ", "1. ", "2) " or "(3) ". Only markers at the start are
     * removed, so text that merely contains a number or a dash — "3 days" or
     * "reluctant — unwilling" — is left alone.
     */
    private const string MARKER = '/^\s*(?:(?:[•‣⁃·◦▪●∙]\s*|[-+–—*]\s+|\d{1,4}[.)](?!\d)\s*|\(\d{1,4}\)\s*|\([a-zA-Z]\)\s*|[a-zA-Z][.)](?![a-zA-Z])\s*){1,4})/u';

    /**
     * Split pasted text into cleaned entries: one per line, blank lines
     * ignored.
     *
     * @return list<string>
     */
    public static function split(string $text): array
    {
        $entries = [];

        foreach (self::lines($text) as $line) {
            $entry = self::cleanLine($line);

            if ($entry !== '') {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * Reduce one line to a clean entry: a leading marker removed, tabs and
     * repeated spaces turned into a single space, and the ends trimmed. A line
     * that holds several lines of pasted text is joined into one.
     */
    public static function cleanLine(string $line): string
    {
        $line = str_replace(["\t", "\u{00A0}"], ' ', $line);
        $line = preg_replace(self::MARKER, '', $line) ?? $line;
        $line = preg_replace('/\s+/u', ' ', $line) ?? $line;

        return trim($line);
    }

    /**
     * Split text on any line ending computers produce, keeping empty lines so
     * the caller can tell blank lines apart from missing ones.
     *
     * @return list<string>
     */
    private static function lines(string $text): array
    {
        $text = str_replace(["\r\n", "\r", "\u{2028}", "\u{2029}"], "\n", $text);
        $lines = explode("\n", $text);

        // A line break at the end finishes the last line; it does not start a
        // line of its own. Popping keeps the keys in order, so the result is
        // still a list.
        if (end($lines) === '') {
            array_pop($lines);
        }

        return $lines;
    }
}
