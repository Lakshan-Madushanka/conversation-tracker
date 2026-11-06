<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Support\CustomReviewStatusRules;
use Illuminate\Support\Facades\DB;

/**
 * Empties one review. Nothing else is touched, so no item's marks, ratings,
 * schedule or session changes, and no other review loses anything.
 *
 * A review with nothing in it is `Empty` and stays exactly where it was,
 * because the learner may still mean to fill it.
 */
final class ClearCustomReview
{
    /** @return int how many items were removed */
    public function handle(CustomReview $review): int
    {
        return DB::transaction(function () use ($review): int {
            $count = CustomReviewItem::query()
                ->where('custom_review_id', $review->id)
                ->delete();

            CustomReviewStatusRules::followItemCount($review, 0);

            return $count;
        });
    }
}
