<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CustomReviewStatus;
use App\Models\CustomReview;

/**
 * The two status moves the app makes on its own, in one place.
 *
 * The status follows the item count in both directions and nowhere else:
 * losing the last item empties a review whatever the learner marked it, and
 * the first item to arrive turns an empty review into a draft. A review the
 * learner marked `Finished` keeps that mark while it still holds items —
 * only their own control moves it, and that arrives in Step 2.
 *
 * Every write that changes a review's items calls this, so no path can leave
 * a status disagreeing with what the review holds.
 */
final readonly class CustomReviewStatusRules
{
    public static function followItemCount(CustomReview $review, int $itemCount): void
    {
        $expected = self::statusFor($review->status, $itemCount);

        if ($expected !== $review->status) {
            $review->status = $expected;
            $review->save();
        }
    }

    /** The status a review holding this many items should be in. */
    private static function statusFor(CustomReviewStatus $current, int $itemCount): CustomReviewStatus
    {
        if ($itemCount === 0) {
            return CustomReviewStatus::Empty;
        }

        return $current === CustomReviewStatus::Finished
            ? CustomReviewStatus::Finished
            : CustomReviewStatus::Draft;
    }
}
