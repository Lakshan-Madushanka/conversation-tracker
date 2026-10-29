<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The short plain-text preview shown for a grammar note in its collection's
 * list: the first lines of the note, with the formatting removed, cut at a
 * sensible place and marked with an ellipsis when anything was lost.
 *
 * The preview is worked out from the note's own plain text, never from its
 * markup, so nothing unsafe can reach the list.
 */
final readonly class NotePreview
{
    /** About how many characters a preview keeps. */
    public const int LENGTH = 160;

    public static function of(string $text, int $length = self::LENGTH): string
    {
        $plain = self::plain($text);

        if (mb_strlen($plain) <= $length) {
            return $plain;
        }

        return self::cut($plain, $length).'…';
    }

    /** The note's words in one line: line breaks and extra spaces flattened. */
    private static function plain(string $text): string
    {
        // Older rows hold formatted text rather than plain words; the markup is
        // reduced first so only the words reach the list.
        $stripped = self::holdsMarkup($text)
            ? RichText::toPlainText($text)
            : RichText::normalizePlainText($text);

        return trim((string) preg_replace('/\s+/u', ' ', $stripped));
    }

    /** Whether a note still carries its formatting instead of plain words. */
    private static function holdsMarkup(string $text): bool
    {
        return preg_match('/<\s*(\/\s*)?[a-zA-Z][a-zA-Z0-9]*/', $text) === 1;
    }

    /**
     * Cuts a plain string to its length, stepping back to the last space when
     * the cut would land inside a word.
     */
    private static function cut(string $text, int $length): string
    {
        $cut = mb_substr($text, 0, $length);
        $after = trim(mb_substr($text, $length, 1));

        if ($after === '') {
            return rtrim($cut);
        }

        $space = mb_strrpos($cut, ' ');

        return $space === false ? $cut : rtrim(mb_substr($cut, 0, $space));
    }
}
