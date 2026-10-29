<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * The one place that decides why a saved table row still needs the learner's
 * attention, and when it does not. The vocabulary and phrase collections (and
 * the mistakes box) all read this rule, so the reason a row is named — and the
 * wording of it — is written here and nowhere else.
 *
 * This is the server side of `resources/js/lib/feedback-table.ts`.
 */
final class RowAttention
{
    /** What the row says when its text held a separator too many to split. */
    public const string MORE_THAN_ONE = 'More than one separator found. Please split it yourself.';

    /** What a vocabulary row says when its meaning is simply missing. */
    public const string ADD_MEANING = 'Add the meaning.';

    /** What a mistake row says when its correction is simply missing. */
    public const string ADD_CORRECTION = 'Add the correction.';

    /**
     * Why one stored row needs attention, from its own content: the row's
     * subject, the column whose emptiness asks for attention, and — for
     * phrases — the example of the row.
     *
     * A phrase without a meaning or use is normal and never asks for
     * attention; only a line that could not be split does, because the whole
     * line then sits in the phrase column with nothing else.
     *
     * @return string|null null when the row holds everything it needs
     */
    public static function reason(
        FeedbackType $type,
        string $subject,
        string $attentionColumn,
        string $example = '',
    ): ?string {
        if (trim($attentionColumn) !== '') {
            return null;
        }

        $unsplittable = self::countSeparators($subject) > 1;

        return match ($type) {
            FeedbackType::Phrase => $unsplittable && trim($example) === ''
                ? self::MORE_THAN_ONE
                : null,
            FeedbackType::Vocabulary => $unsplittable
                ? self::MORE_THAN_ONE
                : self::ADD_MEANING,
            FeedbackType::Mistake => $unsplittable
                ? self::MORE_THAN_ONE
                : self::ADD_CORRECTION,
            default => null,
        };
    }

    /** How many separators a cleaned subject holds. */
    public static function countSeparators(string $subject): int
    {
        return FeedbackTable::countSeparators($subject);
    }
}
