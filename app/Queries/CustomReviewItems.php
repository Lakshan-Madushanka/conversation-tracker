<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;

/**
 * Which of the items on screen a review already holds, read in bulk.
 *
 * One lookup covers a whole page of collection items or every question in a
 * session, so the number of queries never grows with the list.
 */
final readonly class CustomReviewItems
{
    /**
     * The subset of these identities the review holds.
     *
     * @param  list<string>  $identities
     * @return array<string, true>
     */
    public function among(CustomReview $review, FeedbackType $kind, array $identities): array
    {
        $identities = array_values(array_unique(array_filter(
            $identities,
            fn (string $identity): bool => $identity !== '',
        )));

        if ($identities === []) {
            return [];
        }

        /** @var list<string> $found */
        $found = CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->where('kind', $kind->value)
            ->whereIn('item_identity', $identities)
            ->pluck('item_identity')
            ->all();

        return array_fill_keys($found, true);
    }

    /** Whether one item, such as the one open in a panel, is in the review. */
    public function has(CustomReview $review, FeedbackType $kind, string $identity): bool
    {
        return CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->where('kind', $kind->value)
            ->where('item_identity', $identity)
            ->exists();
    }

    /** How many items a review holds. */
    public function count(CustomReview $review): int
    {
        return $this->counts([$review->id])[$review->id] ?? 0;
    }

    /**
     * How many items each of these reviews holds, in one grouped query, so the
     * number of queries never grows with the number of reviews. Reviews with
     * nothing in them are left out.
     *
     * @param  list<int>  $reviewIds
     * @return array<int, int>
     */
    public function counts(array $reviewIds): array
    {
        if ($reviewIds === []) {
            return [];
        }

        /** @var array<int, int> $counts */
        $counts = CustomReviewItem::query()
            ->whereIn('custom_review_id', $reviewIds)
            ->selectRaw('custom_review_id, COUNT(*) as aggregate')
            ->groupBy('custom_review_id')
            ->pluck('aggregate', 'custom_review_id')
            ->all();

        return $counts;
    }

    /**
     * How many items a review holds of each kind, in one grouped query, so the
     * number of queries never grows with the number of kinds. Kinds the review
     * holds nothing of are left out.
     *
     * @return array<string, int>
     */
    public function countsByKind(CustomReview $review): array
    {
        /** @var array<string, int> $counts */
        $counts = CustomReviewItem::query()
            ->where('custom_review_id', $review->id)
            ->selectRaw('kind, COUNT(*) as aggregate')
            ->groupBy('kind')
            ->pluck('aggregate', 'kind')
            ->all();

        return $counts;
    }

    /**
     * The questions of a session that the review already holds.
     *
     * @param  list<int>  $questionIds
     * @return list<int>
     */
    public function questions(CustomReview $review, array $questionIds): array
    {
        $found = $this->among(
            $review,
            FeedbackType::Question,
            array_map(fn (int $id): string => (string) $id, $questionIds),
        );

        return array_map(intval(...), array_keys($found));
    }
}
