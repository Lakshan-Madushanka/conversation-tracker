<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Normalizer;

/**
 * The one place that decides what a search sees: words without their accents,
 * case or extra spaces. The same rules run when content is saved, when older
 * rows are backfilled and when a query is matched, so the three can never
 * disagree.
 *
 * A search with several words asks for all of them in the same item. Wildcards
 * the learner typed (`%` and `_`) are ordinary text; `!` is the escape
 * character because SQLite has no default one and MySQL's backslash would need
 * different escaping in each dialect.
 */
final class SearchText
{
    /** How many non-space characters a search needs before it runs. */
    public const int MIN_LENGTH = 2;

    /** The longest search the Library keeps. */
    public const int MAX_LENGTH = 100;

    /** How much text around a match the Library shows. */
    public const int SNIPPET_RADIUS = 80;

    /**
     * Reduce text to what search compares: decomposed accents removed, lower
     * case, and every run of whitespace turned into one space.
     */
    public static function normalize(string $text): string
    {
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_KD);
        $text = $decomposed === false ? $text : $decomposed;
        $text = (string) preg_replace('/\p{M}+/u', '', $text);
        $text = mb_strtolower($text, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * The separate words a search asks for, already normalised. A word that
     * appears twice is asked for once.
     *
     * @return list<string>
     */
    public static function terms(string $search): array
    {
        $normalized = self::normalize($search);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            array_unique(explode(' ', $normalized)),
            fn (string $term): bool => $term !== '',
        ));
    }

    /** The `LIKE` pattern for one term, with its own wildcards escaped. */
    public static function likePattern(string $term): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
    }

    /**
     * Narrow a query to rows whose stored search text holds every term.
     *
     * @param  Builder<covariant Model>  $query
     * @param  literal-string  $column  a column the app itself names, never input
     * @param  list<string>  $terms
     */
    public static function whereAll(Builder $query, string $column, array $terms): void
    {
        foreach ($terms as $term) {
            $query->whereRaw(
                "{$column} LIKE ? ESCAPE '!'",
                [self::likePattern($term)],
            );
        }
    }

    /**
     * Whether one piece of text holds every term.
     *
     * @param  list<string>  $terms
     */
    public static function matches(string $text, array $terms): bool
    {
        if ($terms === []) {
            return false;
        }

        $normalized = self::normalize($text);

        foreach ($terms as $term) {
            if (! str_contains($normalized, $term)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A short piece of the original text around its first match, split into
     * plain pieces and matched pieces. The original spelling is kept, so an
     * accent the learner wrote is still shown while `cafe` finds it.
     *
     * @param  list<string>  $terms
     * @return list<array{text: string, match: bool}>
     */
    public static function snippet(
        string $text,
        array $terms,
        int $radius = self::SNIPPET_RADIUS,
    ): array {
        if ($text === '') {
            return [];
        }

        [$normalized, $map] = self::normalizeWithMap($text);
        $ranges = self::ranges($normalized, self::terms(implode(' ', $terms)));

        if ($ranges === []) {
            return [['text' => self::shorten($text, $radius * 2), 'match' => false]];
        }

        $characters = mb_str_split($text);
        $length = count($characters);

        $firstMatch = $map[$ranges[0][0]];
        $lastMatch = $map[$ranges[count($ranges) - 1][1] - 1] + 1;

        $start = max(0, $firstMatch - $radius);
        $end = min($length, $lastMatch + $radius);

        // Do not begin or end on blank space when the window allows it.
        while ($start < $firstMatch && preg_match('/^\s$/u', $characters[$start]) === 1) {
            $start += 1;
        }

        while ($end > $lastMatch && preg_match('/^\s$/u', $characters[$end - 1]) === 1) {
            $end -= 1;
        }

        $segments = [];
        $cursor = $start;

        foreach ($ranges as [$rangeStart, $rangeEnd]) {
            $originalStart = $map[$rangeStart];
            $originalEnd = $map[$rangeEnd - 1] + 1;

            if ($originalEnd <= $start || $originalStart >= $end) {
                continue;
            }

            $originalStart = max($originalStart, $start);
            $originalEnd = min($originalEnd, $end);

            if ($originalStart > $cursor) {
                $segments[] = [
                    'text' => implode('', array_slice(
                        $characters,
                        $cursor,
                        $originalStart - $cursor,
                    )),
                    'match' => false,
                ];
            }

            $segments[] = [
                'text' => implode('', array_slice(
                    $characters,
                    $originalStart,
                    $originalEnd - $originalStart,
                )),
                'match' => true,
            ];

            $cursor = $originalEnd;
        }

        if ($cursor < $end) {
            $segments[] = [
                'text' => implode('', array_slice($characters, $cursor, $end - $cursor)),
                'match' => false,
            ];
        }

        // The window is a piece of the whole text, so say where words were cut.
        if ($start > 0 && $segments !== []) {
            $segments[0] = [
                'text' => '…'.$segments[0]['text'],
                'match' => $segments[0]['match'],
            ];
        }

        if ($end < $length && $segments !== []) {
            $last = count($segments) - 1;
            $segments[$last] = [
                'text' => $segments[$last]['text'].'…',
                'match' => $segments[$last]['match'],
            ];
        }

        return $segments;
    }

    /**
     * The normalised text, plus the original character index each normalised
     * character came from. The map lets a match found in the normalised text be
     * highlighted in the words the learner actually wrote.
     *
     * @return array{0: string, 1: list<int>}
     */
    private static function normalizeWithMap(string $text): array
    {
        $normalized = '';
        $map = [];
        $lastWasSpace = false;

        foreach (mb_str_split($text) as $index => $character) {
            $piece = self::normalizeCharacter($character);

            if ($piece === '') {
                continue;
            }

            if ($piece === ' ') {
                if ($normalized === '' || $lastWasSpace) {
                    continue;
                }

                $lastWasSpace = true;
            } else {
                $lastWasSpace = false;
            }

            foreach (mb_str_split($piece) as $normalizedCharacter) {
                $normalized .= $normalizedCharacter;
                $map[] = $index;
            }
        }

        $normalized = rtrim($normalized);

        return [$normalized, array_slice($map, 0, mb_strlen($normalized))];
    }

    /** One character reduced to what search sees, keeping a space as a space. */
    private static function normalizeCharacter(string $character): string
    {
        if (preg_match('/^\s$/u', $character) === 1) {
            return ' ';
        }

        $decomposed = Normalizer::normalize($character, Normalizer::FORM_KD);
        $text = $decomposed === false ? $character : $decomposed;
        $text = (string) preg_replace('/\p{M}+/u', '', $text);

        return mb_strtolower($text, 'UTF-8');
    }

    /**
     * Every place the terms appear, as character ranges in the normalised
     * text, with overlapping and touching ranges joined.
     *
     * @param  list<string>  $terms
     * @return list<array{0: int, 1: int}>
     */
    private static function ranges(string $normalized, array $terms): array
    {
        $ranges = [];
        $length = mb_strlen($normalized);

        foreach ($terms as $term) {
            if ($term === '') {
                continue;
            }

            $termLength = mb_strlen($term);
            $offset = 0;

            while ($offset <= $length) {
                $position = mb_strpos($normalized, $term, $offset);

                if ($position === false) {
                    break;
                }

                $ranges[] = [$position, $position + $termLength];
                $offset = $position + 1;
            }
        }

        if ($ranges === []) {
            return [];
        }

        sort($ranges);

        $merged = [$ranges[0]];

        foreach (array_slice($ranges, 1) as [$start, $end]) {
            $lastIndex = count($merged) - 1;
            [$lastStart, $lastEnd] = $merged[$lastIndex];

            if ($start <= $lastEnd) {
                $merged[$lastIndex] = [$lastStart, max($lastEnd, $end)];

                continue;
            }

            $merged[] = [$start, $end];
        }

        return $merged;
    }

    private static function shorten(string $text, int $length): string
    {
        return mb_strlen($text) > $length
            ? mb_substr($text, 0, $length).'…'
            : $text;
    }
}
