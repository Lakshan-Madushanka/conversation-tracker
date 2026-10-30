<?php

declare(strict_types=1);

namespace App\Support;

/**
 * The one place that decides whether two mistake rows are the same mistake.
 *
 * A mistake is the pair of what the learner said and the correction: the same
 * pair, ignoring capitals, accents, extra spaces and formatting, is one
 * group. The same correction for two different mistakes stays two groups,
 * because the pair is what identifies the mistake. A row without a
 * correction is treated as the pair of what was said and an empty column, so
 * the same words with and without a correction are two groups.
 *
 * The pair is turned into a stable key that names the mistake in the page's
 * address: opaque and derived only from the normalised pair, so a key that
 * the grouping chose once still opens the same group later. Review and
 * Progress can reuse this when they point at a mistake.
 */
final class MistakeIdentity
{
    /** The separator between the pair's two parts inside a raw pair. */
    private const string PAIR_SEPARATOR = "\u{0}";

    /**
     * The stable, opaque key of one mistake: the normalised pair. Two rows
     * share it whenever only capitals, accents, extra spaces or formatting
     * differ between what was said, or between the corrections.
     */
    public static function pairKey(string $said, string $correction): string
    {
        $pair = SearchText::normalize($said).self::PAIR_SEPARATOR.SearchText::normalize($correction);

        return 'mistake-'.substr(sha1($pair), 0, 24);
    }

    /** Whether the pair holds nothing at all: a row with nothing to gather. */
    public static function isBlank(string $said, string $correction): bool
    {
        return SearchText::normalize($said) === ''
            && SearchText::normalize($correction) === '';
    }
}
