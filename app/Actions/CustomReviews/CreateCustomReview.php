<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\CustomReviewStatus;
use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Queries\NextCustomReviewName;
use App\Support\CustomReviewKinds;
use Illuminate\Support\Facades\DB;

/**
 * Starts a custom review with the name and kinds the learner chose.
 *
 * The dialog always sends both. A path that asks nothing — adding items while
 * no review is being built — creates the review without a name, and it takes
 * the next number no review of theirs already carries. It starts `Empty`
 * because nothing is in it; choosing every kind is stored as no choice at all.
 *
 * Naming and the kinds choice are the app's only writes here. No item, mark,
 * rating or session is read or touched.
 */
final readonly class CreateCustomReview
{
    public function __construct(
        private NextCustomReviewName $nextName,
    ) {}

    /**
     * @param  list<FeedbackType>|null  $kinds  the kinds the review allows, or
     *                                          null for every kind
     */
    public function handle(?string $name = null, ?array $kinds = null): CustomReview
    {
        return DB::transaction(function () use ($name, $kinds): CustomReview {
            $review = new CustomReview;
            $review->name = $name ?? $this->nextName->handle();
            $review->status = CustomReviewStatus::Empty;
            $review->allowed_kinds = $kinds === null ? null : CustomReviewKinds::storedChoice($kinds);
            $review->save();

            return $review;
        });
    }
}
