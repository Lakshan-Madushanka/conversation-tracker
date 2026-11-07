<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\FeedbackType;

/**
 * Why an item already in a review cannot be used yet, in the one wording the
 * review page and the eligible Collections view share.
 *
 * Eligibility mirrors Review: a word or phrase needs a meaning, a mistake
 * needs what was said and a correction, a question needs a natural version,
 * a grammar note needs a usable bold part or quote, and every item must come
 * from a finished session that is not archived. Nothing is stored: the answer
 * is read again each time the item is shown.
 */
final class CustomReviewEligibility
{
    public const string ADD_MEANING = 'Add a meaning to use this.';

    public const string ADD_CORRECTION = 'Add the correction to use this.';

    public const string SPLIT_CORRECTION = 'Split this into a correction first.';

    public const string ADD_NATURAL_VERSION = 'Add a natural version to use this.';

    public const string BOLD_KEY_PART = 'Bold the key part to use this.';

    public const string NOT_AVAILABLE = 'Not available right now.';

    /**
     * The reason an item cannot be used, or null when it can.
     *
     * `$usable` is the kind's own rule — a meaning, a correction, a natural
     * version, or a note that can make a blank. `$splitBroke` says a table
     * line could not be split into subject and meaning/correction, which is
     * the more specific reason for those items.
     */
    public static function reason(
        FeedbackType $kind,
        bool $available,
        bool $usable,
        bool $splitBroke = false,
    ): ?string {
        if (! $available) {
            return self::NOT_AVAILABLE;
        }

        if ($usable) {
            return null;
        }

        return match ($kind) {
            FeedbackType::Vocabulary, FeedbackType::Phrase => $splitBroke
                ? self::SPLIT_CORRECTION
                : self::ADD_MEANING,
            FeedbackType::Mistake => $splitBroke
                ? self::SPLIT_CORRECTION
                : self::ADD_CORRECTION,
            FeedbackType::Question => self::ADD_NATURAL_VERSION,
            FeedbackType::GrammarNote => self::BOLD_KEY_PART,
            FeedbackType::NaturalVersion => null,
        };
    }
}
