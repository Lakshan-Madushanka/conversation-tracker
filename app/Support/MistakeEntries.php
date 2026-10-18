<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The rules for the mistakes box: how a pasted line splits into the three
 * columns (what I said, the correction and an optional why), and why a row
 * still needs the learner's attention.
 *
 * This mirrors `resources/js/lib/mistakes.ts`, so the browser and the server
 * always agree on what a paste becomes. The server applies the same rules on
 * save, so what is stored always matches what the learner saw.
 */
final class MistakeEntries
{
    /** The most rows one mistakes box can hold. */
    public const int MAX_ROWS = 200;

    /** The longest single cell, in characters. */
    public const int MAX_CELL_LENGTH = 1000;

    /** What the column says when a correction is missing. */
    public const string ATTENTION_ADD_CORRECTION = 'Add the correction.';

    /** What the column says when the line held more than one separator. */
    public const string ATTENTION_MORE_THAN_ONE = 'More than one separator found. Please split it yourself.';

    /**
     * A separator inside a cleaned line. Arrows count wherever they appear; a
     * dash (single, double, en or em) counts only with a space on both sides,
     * so hyphenated words such as "3-day" stay whole.
     */
    private const string SEPARATOR = '/(?:→|->|=>|(?<=\s)-{1,2}(?=\s)|(?<=\s)[–—](?=\s))/u';

    /**
     * Split one pasted line into its parts. Cleaning runs first, so a leading
     * bullet is a bullet, never a separator.
     *
     * - one separator: the text before it is what I said, the text after it is
     *   the correction;
     * - no separator, or more than one: the whole cleaned line stays as what I
     *   said, so nothing is guessed at and nothing is lost.
     *
     * @return array{text: string, correction_html: string, correction_text: string, reason: string}
     */
    public static function parseLine(string $line): array
    {
        $cleaned = ListEntries::cleanLine($line);
        $matches = self::separatorMatches($cleaned);

        if (count($matches) === 1) {
            $offset = $matches[0][1];
            $length = strlen($matches[0][0]);
            $text = trim(substr($cleaned, 0, $offset));
            $correction = trim(substr($cleaned, $offset + $length));

            return [
                'text' => $text,
                'correction_html' => self::escape($correction),
                'correction_text' => $correction,
                'reason' => '',
            ];
        }

        return [
            'text' => $cleaned,
            'correction_html' => '',
            'correction_text' => '',
            'reason' => '',
        ];
    }

    /** How many separators a cleaned line holds. */
    public static function countSeparators(string $line): int
    {
        return count(self::separatorMatches(ListEntries::cleanLine($line)));
    }

    /**
     * Why a row needs the learner's attention, worked out from its content
     * rather than stored. A row with a correction is fine.
     */
    public static function attention(string $text, string $correctionText): ?string
    {
        if (trim($correctionText) !== '') {
            return null;
        }

        if (self::countSeparators($text) > 1) {
            return self::ATTENTION_MORE_THAN_ONE;
        }

        return self::ATTENTION_ADD_CORRECTION;
    }

    /**
     * Every separator in a line, with its byte offset, so a match can be cut
     * out of the line with substr and stay correct on UTF-8 text.
     *
     * @return list<array{0: string, 1: int}>
     */
    private static function separatorMatches(string $line): array
    {
        $found = [];

        if (preg_match_all(self::SEPARATOR, $line, $matches, PREG_OFFSET_CAPTURE) === false) {
            return [];
        }

        foreach ($matches[0] as $match) {
            $found[] = [$match[0], $match[1]];
        }

        return $found;
    }

    private static function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
