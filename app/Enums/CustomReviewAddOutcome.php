<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What asking for items to be gathered came to.
 *
 * `Added` means the items are in the review. `NeedsReviewChoice` means several
 * reviews are still being built and only the learner can say which one, and
 * `NeedsConfirmation` means the add would take a review past its recommended
 * size. The two questions are asked rather than guessed, and the learner's
 * answer always leads to `Added`. `RefusedMaximum` means the add would take
 * the review past the most it may hold; nothing is written, and no answer
 * changes that. `RefusedKind` means the review has narrowed its kinds and the
 * items are not among them; nothing is written, and no answer changes that.
 */
enum CustomReviewAddOutcome: string
{
    case Added = 'added';

    case NeedsReviewChoice = 'needs_review_choice';

    case NeedsConfirmation = 'needs_confirmation';

    case RefusedMaximum = 'refused_maximum';

    case RefusedKind = 'refused_kind';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $outcome): string => $outcome->value, self::cases());
    }
}
