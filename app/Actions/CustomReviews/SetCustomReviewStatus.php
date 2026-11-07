<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\CustomReviewStatus;
use App\Models\CustomReview;
use App\Queries\CustomReviewItems;
use Illuminate\Validation\ValidationException;

/**
 * The learner's own Draft-or-Finished move, and nothing else.
 *
 * A review with no items cannot be marked finished, so the control can explain
 * what is missing without the request quietly succeeding. A review still
 * holding items can move either way at any time; the app's automatic Empty
 * status remains the one exception.
 */
final readonly class SetCustomReviewStatus
{
    public function __construct(
        private CustomReviewItems $items,
    ) {}

    /**
     * @throws ValidationException when the review has nothing in it
     */
    public function handle(CustomReview $review, CustomReviewStatus $status): CustomReview
    {
        if ($this->items->count($review) === 0) {
            throw ValidationException::withMessages([
                'status' => 'Add items before marking this review finished.',
            ]);
        }

        if ($review->status !== $status) {
            $review->status = $status;
            $review->save();
        }

        return $review->refresh();
    }
}
