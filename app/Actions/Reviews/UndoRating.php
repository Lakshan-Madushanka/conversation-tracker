<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\FeedbackType;
use App\Models\Rating;
use App\Models\Review;

/**
 * Takes back the last rating the learner gave in this review.
 *
 * Only the named item's rating is removed, so an accidental tap can be undone
 * without touching anything else. Leaving the review later keeps every other
 * rating exactly as it was.
 */
final readonly class UndoRating
{
    public function handle(Review $review, FeedbackType $kind, string $itemIdentity): void
    {
        Rating::query()
            ->where('review_id', $review->id)
            ->where('kind', $kind->value)
            ->where('item_identity', $itemIdentity)
            ->delete();
    }
}
