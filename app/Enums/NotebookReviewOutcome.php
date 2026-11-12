<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What Create review and practise came to.
 *
 * `Created` means the new review exists and its first attempt has started.
 * `CreatedStartFailed` means the review and its items were saved but the
 * attempt could not begin, so the learner can retry from that same review.
 * The other answers ask for something before anything is written: a smaller
 * selection, a size confirmation, an eligible item, or a fresh candidate list.
 */
enum NotebookReviewOutcome: string
{
    case Created = 'created';

    case CreatedStartFailed = 'created_start_failed';

    case NeedsSelection = 'needs_selection';

    case NeedsConfirmation = 'needs_confirmation';

    case TooManySelected = 'too_many_selected';

    case NoCandidates = 'no_candidates';

    case NoEligible = 'no_eligible';

    case StaleSelection = 'stale_selection';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $outcome): string => $outcome->value, self::cases());
    }
}
