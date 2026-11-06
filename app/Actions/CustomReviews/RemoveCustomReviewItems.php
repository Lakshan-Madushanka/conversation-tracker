<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\FeedbackType;
use App\Models\CustomReview;
use App\Models\CustomReviewItem;
use App\Queries\CustomReviewItems;
use App\Support\CustomReviewStatusRules;
use Illuminate\Support\Facades\DB;

/**
 * Takes items out of one review, by identity. Removing something that is not
 * there is not an error; the answer simply says nothing was removed, so a
 * repeated request stays safe.
 *
 * A review that has lost its last item is `Empty` again whatever it was
 * marked, because there is nothing left to look at.
 */
final readonly class RemoveCustomReviewItems
{
    public function __construct(
        private CustomReviewItems $items,
    ) {}

    /**
     * @param  list<string>  $identities
     * @return array{removed: list<string>, total: int}
     */
    public function handle(CustomReview $review, FeedbackType $kind, array $identities): array
    {
        $identities = array_values(array_unique(array_filter(
            $identities,
            fn (string $identity): bool => $identity !== '',
        )));

        return DB::transaction(function () use ($review, $kind, $identities): array {
            if ($identities === []) {
                return ['removed' => [], 'total' => $this->items->count($review)];
            }

            /** @var list<string> $removed */
            $removed = CustomReviewItem::query()
                ->where('custom_review_id', $review->id)
                ->where('kind', $kind->value)
                ->whereIn('item_identity', $identities)
                ->pluck('item_identity')
                ->all();

            CustomReviewItem::query()
                ->where('custom_review_id', $review->id)
                ->where('kind', $kind->value)
                ->whereIn('item_identity', $identities)
                ->delete();

            $total = $this->items->count($review);

            CustomReviewStatusRules::followItemCount($review, $total);

            return [
                'removed' => $removed,
                'total' => $total,
            ];
        });
    }
}
