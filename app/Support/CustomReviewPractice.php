<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;

/**
 * The one answer to "can this review be practised", shared by the card, the
 * review's page and the action that will start an attempt.
 *
 * A review is ready only when the learner has marked it Finished and at least
 * one of its items can be used right now. Everywhere that asks the question
 * reads this, so the three places can never disagree.
 */
final class CustomReviewPractice
{
    /** What an Empty review is waiting for. */
    public const string NEEDS_ITEMS = 'Add items to use this review.';

    /** What a Draft review is waiting for. */
    public const string NEEDS_FINISHING = 'Mark this review Finished to practise it.';

    /** What a Finished review says when none of its items can be used. */
    public const string NOT_AVAILABLE = 'Its items are unavailable right now.';

    /** The action offered instead of starting while an attempt is running. */
    public const string RESUME = 'Resume attempt';

    /**
     * What an attempt says about a kind it cannot show anything of right now,
     * because every item of it sits in an archived session.
     */
    public static function leftOut(FeedbackType $kind): string
    {
        return $kind->customReviewLabel().' are not in this attempt right now, because their items are archived.';
    }

    /** The start action's words, naming how many items the attempt will hold. */
    public static function startLabel(int $itemCount): string
    {
        return 'Start practice · '.$itemCount.' '.($itemCount === 1 ? 'item' : 'items');
    }

    public static function canStart(CustomReviewStatus $status, bool $hasEligibleItem): bool
    {
        return $status === CustomReviewStatus::Finished && $hasEligibleItem;
    }

    /**
     * What still stands between the review and practice, or null when nothing
     * does.
     */
    public static function note(CustomReviewStatus $status, bool $hasEligibleItem): ?string
    {
        return match ($status) {
            CustomReviewStatus::Empty => self::NEEDS_ITEMS,
            CustomReviewStatus::Draft => self::NEEDS_FINISHING,
            CustomReviewStatus::Finished => $hasEligibleItem ? null : self::NOT_AVAILABLE,
        };
    }
}
