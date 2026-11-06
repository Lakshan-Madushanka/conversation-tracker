<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Models\Review;
use Carbon\CarbonImmutable;

/**
 * Ends a review before every item has a rating, keeping what was already rated.
 *
 * The moment the learner stopped is the only thing written, so the sitting can
 * appear in recent results as what it was — a short look, not a completed
 * review. It never becomes the day's review, never touches a session, a mark or
 * the streak, and rating more items after it changes nothing else.
 */
final class FinishReviewEarly
{
    public function handle(Review $review): Review
    {
        if ($review->finished_at !== null) {
            return $review;
        }

        $review->finished_at = CarbonImmutable::now();
        $review->save();

        return $review;
    }
}
