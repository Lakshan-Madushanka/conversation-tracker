<?php

declare(strict_types=1);

namespace App\Actions\CustomReviewAttempts;

use App\Enums\CustomReviewAttemptStatus;
use App\Models\CustomReview;
use App\Models\CustomReviewAttempt;

/**
 * Removes an attempt that was created but never began, so an empty shell never
 * lingers as a phantom entry in the history or the card.
 *
 * Only a Not started attempt with no items is removed: anything the learner has
 * begun keeps its number and its ratings, and is never touched.
 */
final class PruneEmptyAttempts
{
    public function handle(CustomReview $review): int
    {
        return CustomReviewAttempt::query()
            ->where('custom_review_id', $review->id)
            ->where('status', CustomReviewAttemptStatus::NotStarted->value)
            ->whereDoesntHave('items')
            ->delete();
    }
}
