<?php

declare(strict_types=1);

namespace App\Actions\CustomReviews;

use App\Enums\FeedbackType;
use App\Enums\ItemAvailability;
use App\Models\CustomReview;
use App\Models\CustomReviewAttemptItem;
use App\Models\CustomReviewItem;
use App\Queries\CustomReviewAvailability;
use App\Queries\CustomReviewItems;
use App\Support\CustomReviewStatusRules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Removes review references whose item no longer exists at all.
 *
 * An item that is only archived still exists, so it stays and is shown as not
 * available right now. An item whose last session was deleted has nothing left
 * to point at, so its reference is removed for good from every review that
 * holds it, instead of leaving a ghost row or an empty line.
 *
 * Whether an item is gone comes from `CustomReviewAvailability`, the same read
 * the review's page, its card and its attempts use, so a deletion is never
 * judged by a second rule.
 *
 * Two entries lead here. `handle()` sweeps every reference at once and is the
 * repair a review read performs, so a view opened after the app was offline
 * still catches up. `handleFor()` covers the deletion that is happening right
 * now, called inside the delete transaction by the actions that remove items,
 * so every feature reacts to a deletion at the moment it happens.
 *
 * Both leave the same marks: the references are removed, the attempts that
 * recorded the item get their history's Deleted marker, and a review that has
 * lost its last item becomes `Empty` like any other removal.
 */
final readonly class PruneCustomReviewItems
{
    public function __construct(
        private CustomReviewItems $items,
        private CustomReviewAvailability $availability,
    ) {}

    /** @return int how many references were removed */
    public function handle(): int
    {
        /** @var array<string, list<string>> $grouped */
        $grouped = CustomReviewItem::query()
            ->get(['kind', 'item_identity'])
            ->groupBy(fn (CustomReviewItem $item): string => $item->kind->value)
            ->map(fn (Collection $rows): array => $rows
                ->pluck('item_identity')
                ->unique()
                ->values()
                ->all())
            ->all();

        return $this->remove($grouped);
    }

    /**
     * The references to items that have just been deleted, removed at once.
     *
     * @param  array<string, list<string>>  $identitiesByKind  keyed by `FeedbackType` value
     * @return int how many references were removed
     */
    public function handleFor(array $identitiesByKind): int
    {
        return $this->remove($identitiesByKind);
    }

    /**
     * Removing every candidate that the availability read calls gone, and
     * marking the attempts that recorded it.
     *
     * @param  array<string, list<string>>  $candidatesByKind
     * @return int how many references were removed
     */
    private function remove(array $candidatesByKind): int
    {
        /** @var array<string, list<string>> $dead */
        $dead = [];

        foreach ($candidatesByKind as $kindValue => $identities) {
            $kind = FeedbackType::tryFrom($kindValue);

            if ($kind === null || ! $kind->isCustomReviewType()) {
                $dead[$kindValue] = $identities;

                continue;
            }

            $states = $this->availability->states($kind, array_values(array_unique($identities)));

            // The identities the availability read calls gone, in the order
            // they were asked for.
            $missing = array_keys(array_filter(
                $states,
                fn (ItemAvailability $state): bool => $state->isMissing(),
            ));

            if ($missing !== []) {
                $dead[$kindValue] = $missing;
            }
        }

        if ($dead === []) {
            return 0;
        }

        return DB::transaction(function () use ($dead): int {
            $affected = [];
            $removed = 0;

            foreach ($dead as $kindValue => $identities) {
                $rows = CustomReviewItem::query()
                    ->where('kind', $kindValue)
                    ->whereIn('item_identity', $identities);

                $affected = array_merge($affected, $rows->pluck('custom_review_id')->all());

                $removed += $rows->delete();

                // Every attempt that recorded this item keeps its snapshot and
                // gains the marker, whether or not a review still holds it.
                CustomReviewAttemptItem::query()
                    ->where('kind', $kindValue)
                    ->whereIn('item_identity', $identities)
                    ->where('is_deleted', false)
                    ->update(['is_deleted' => true]);
            }

            $this->syncStatuses(array_values(array_unique($affected)));

            return $removed;
        });
    }

    /**
     * The reviews that lost a reference, given the status their remaining
     * items tell them to hold. One grouped count covers them all.
     *
     * @param  list<int>  $reviewIds
     */
    private function syncStatuses(array $reviewIds): void
    {
        if ($reviewIds === []) {
            return;
        }

        $counts = $this->items->counts($reviewIds);

        /** @var Collection<int, CustomReview> $reviews */
        $reviews = CustomReview::query()
            ->whereIn('id', $reviewIds)
            ->get();

        foreach ($reviews as $review) {
            CustomReviewStatusRules::followItemCount(
                $review,
                $counts[$review->id] ?? 0,
            );
        }
    }
}
