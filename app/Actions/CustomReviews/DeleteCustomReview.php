<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Models\CustomReview;
use Illuminate\Support\Facades\DB;

/**
 * Delete one custom review for good.
 *
 * The review, the item references it held, its attempts and every snapshot and
 * rating those attempts recorded all go together, in one transaction; the
 * database's own cascade takes the rows below the review, so nothing can be
 * left half-deleted. What the review only pointed at stays exactly as it was:
 * the items, their sessions, marks, schedules and Review ratings are untouched,
 * and so is any other review that holds the same items.
 *
 * The address carries an id rather than a record, because the record may already
 * be gone — a second tab, a double press, an old bookmark. Answering null is
 * then as calm as answering with what was deleted, and writes nothing.
 */
final readonly class DeleteCustomReview
{
    /**
     * @return array{name: string, attempts: int}|null null when it was already gone
     */
    public function handle(int $customReviewId): ?array
    {
        $review = CustomReview::query()->find($customReviewId);

        if (! $review instanceof CustomReview) {
            return null;
        }

        $name = $review->name;
        $attempts = $review->attempts()->count();

        DB::transaction(function () use ($review): void {
            $review->delete();
        });

        return ['name' => $name, 'attempts' => $attempts];
    }
}
