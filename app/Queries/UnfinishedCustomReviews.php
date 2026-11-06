<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\CustomReview;
use Illuminate\Support\Collection;

/**
 * The reviews the learner is still building, and which one an add belongs to.
 *
 * A review is being built while its status is `Empty` or `Draft`. A `Finished`
 * review is never answered here, because the learner has already said it is
 * ready: topping one up happens from its own page.
 */
final readonly class UnfinishedCustomReviews
{
    public function __construct(
        private CustomReviewItems $items,
    ) {}

    /**
     * Every review still being built, the most recently created first, each
     * with the item count that tells the reviews apart on the picker.
     *
     * Counts are gathered for all of them in one query, so the number of
     * queries never grows with the number of reviews.
     *
     * @return list<array{review: CustomReview, item_count: int}>
     */
    public function list(): array
    {
        /** @var Collection<int, CustomReview> $reviews */
        $reviews = CustomReview::query()->beingBuilt()->newestFirst()->get();

        if ($reviews->isEmpty()) {
            return [];
        }

        /** @var list<int> $ids */
        $ids = $reviews->pluck('id')->all();
        $counts = $this->items->counts($ids);

        $choices = [];

        foreach ($reviews as $review) {
            $choices[] = [
                'review' => $review,
                'item_count' => $counts[$review->id] ?? 0,
            ];
        }

        return $choices;
    }

    /**
     * The review a page should mark items against: the one the learner named,
     * or the only one being built, or none when there is nothing to agree on.
     *
     * With several reviews still being built the learner has not chosen one
     * yet, so no item on a collection list can honestly say it is added.
     */
    public function target(?int $reviewId): ?CustomReview
    {
        if ($reviewId !== null) {
            /** @var CustomReview|null $review */
            $review = CustomReview::query()->find($reviewId);

            return $review;
        }

        /** @var Collection<int, CustomReview> $beingBuilt */
        $beingBuilt = CustomReview::query()->beingBuilt()->get();

        return $beingBuilt->count() === 1
            ? $beingBuilt->first()
            : null;
    }
}
