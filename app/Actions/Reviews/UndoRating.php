<?php

declare(strict_types=1);

namespace App\Actions\Reviews;

use App\Enums\FeedbackType;
use App\Models\Rating;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

/**
 * Takes back the last rating the learner gave in this review.
 *
 * Only the named item's rating is removed, so an accidental tap can be undone
 * without touching anything else. The item's schedule is rebuilt from the
 * remaining history in the same transaction, restoring the schedule it had
 * before the rating (or marking it new again when no ratings remain).
 */
final readonly class UndoRating
{
    public function __construct(private SyncReviewSchedule $schedule) {}

    public function handle(Review $review, FeedbackType $kind, string $itemIdentity): void
    {
        DB::transaction(function () use ($review, $kind, $itemIdentity): void {
            Rating::query()
                ->where('review_id', $review->id)
                ->where('kind', $kind->value)
                ->where('item_identity', $itemIdentity)
                ->delete();

            $this->schedule->handle($kind, $itemIdentity);
        });
    }
}
